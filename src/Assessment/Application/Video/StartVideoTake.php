<?php

namespace Src\Assessment\Application\Video;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\Attempts\AttemptContext;
use Src\Assessment\Application\Attempts\AttemptGate;
use Src\Assessment\Domain\VideoTake;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * The candidate starts recording an answer. The first take and each retake the question allows are counted: a take that was given up half way (the
 * browser closed, the connection lost) still counts, and its chunks are deleted. A complete earlier take stays the answer until the new take completes.
 */
final readonly class StartVideoTake
{
    public function __construct(
        private AttemptGate $gate,
        private VideoTakes $videos,
        private VideoTakeRepository $takes,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-started`, `attempt-closed`, `question-locked` or `retake-limit`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, ?string $ip): VideoTake
    {
        /** @var array{0: VideoTake, 1: list<string>} $result */
        $result = $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId): array {
            $question = $this->videos->question($context, $questionId);
            $attemptId = (int) $context->attempt->id;
            $used = $this->takes->ofQuestion($attemptId, $questionId);

            if (count($used) >= ($question->maxRetakes ?? 0) + 1) {
                throw new StateConflict('You have used every recording this question allows.', 'retake-limit');
            }

            $abandoned = [];

            foreach ($used as $take) {
                if ($take->isRecording()) {
                    $abandoned = [...$abandoned, ...$this->chunkPaths($take)];
                    $this->takes->update($take->reject($context->now));
                }
            }

            return [$this->takes->add(VideoTake::start($attemptId, $questionId, count($used) + 1, (int) $question->answerSeconds, $context->now)), $abandoned];
        });

        foreach ($result[1] as $path) {
            $this->deleteQuietly($path);
        }

        return $result[0];
    }

    /** @return list<string> */
    private function chunkPaths(VideoTake $take): array
    {
        return array_map(static fn (int $index): string => VideoTakes::chunkPath((int) $take->id, $index), range(0, max($take->chunkCount - 1, 0)));
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A video chunk could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
