<?php

namespace Src\Assessment\Application\Video;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Src\Assessment\Application\Attempts\AttemptContext;
use Src\Assessment\Application\Attempts\AttemptGate;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\VideoContent;
use Src\Assessment\Domain\VideoTake;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\FileStreams;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * The candidate says the last chunk was sent. The chunks are joined into one file and the take becomes the answer to the question; the take it replaces
 * is rejected and its file deleted at once.
 */
final readonly class CompleteVideoTake
{
    public function __construct(
        private AttemptGate $gate,
        private VideoTakes $videos,
        private VideoTakeRepository $takes,
        private AnswerRepository $answers,
        private FileStorage $files,
        private FileStreams $streams,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-video` when no chunk was sent
     * @throws StateConflict `attempt-not-started`, `attempt-closed`, `question-locked`, `video-take-closed` or `upload-deadline-passed`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, int $takeId, ?string $ip): VideoTake
    {
        /** @var array{0: VideoTake, 1: list<string>} $result */
        $result = $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId, $takeId): array {
            $this->videos->question($context, $questionId);
            $take = $this->videos->take($context, $questionId, $takeId);

            if (! $take->isRecording()) {
                throw new StateConflict('This recording is closed.', 'video-take-closed');
            }

            if ($take->isPastDeadline($context->now)) {
                throw new StateConflict('The time to send this recording has passed.', 'upload-deadline-passed');
            }

            if ($take->chunkCount === 0) {
                throw new BusinessRuleViolation('No part of the recording was sent.', 'invalid-video', extensions: ['errors' => ['file' => ['No part of the recording was sent.']]]);
            }

            $attemptId = (int) $context->attempt->id;
            [$path, $size] = $this->join($take, $attemptId);

            try {
                $replaced = [];

                foreach ($this->takes->ofQuestion($attemptId, $questionId) as $earlier) {
                    if ($earlier->status->value === 'complete') {
                        $replaced[] = (string) $earlier->path;
                        $this->takes->update($earlier->reject($context->now));
                    }
                }

                $complete = $take->complete($path, $size, $context->now);
                $this->takes->update($complete);
                $this->answers->saveVideoAnswer($attemptId, $questionId, (int) $take->id);
            } catch (Throwable $e) {
                $this->deleteQuietly($path);

                throw $e;
            }

            return [$complete, [...$replaced, ...array_map(static fn (int $i): string => VideoTakes::chunkPath((int) $take->id, $i), range(0, $take->chunkCount - 1))]];
        });

        foreach ($result[1] as $path) {
            $this->deleteQuietly($path);
        }

        return $result[0];
    }

    /**
     * @return array{0: string, 1: int} the path of the joined file and its size
     */
    private function join(VideoTake $take, int $attemptId): array
    {
        $first = $this->files->get(VideoTakes::chunkPath((int) $take->id, 0));
        $content = $first === null ? null : VideoContent::detect(substr($first, 0, 16));

        if ($content === null) {
            throw new BusinessRuleViolation('The recording must be a WebM or MP4 video.', 'invalid-video', extensions: ['errors' => ['file' => ['The recording must be a WebM or MP4 video.']]]);
        }

        $path = VideoTakes::VIDEOS.'/'.$attemptId.'/'.bin2hex(random_bytes(16)).'.'.$content['extension'];
        $joined = tmpfile() ?: throw new RuntimeException('A temporary file could not be created.');

        try {
            for ($index = 0; $index < $take->chunkCount; $index++) {
                $chunk = $this->files->get(VideoTakes::chunkPath((int) $take->id, $index))
                    ?? throw new BusinessRuleViolation('A part of the recording is missing.', 'invalid-video', extensions: ['errors' => ['file' => ['A part of the recording is missing.']]]);
                fwrite($joined, $chunk);
            }

            rewind($joined);
            $this->streams->putStream($path, $joined);
        } finally {
            fclose($joined);
        }

        return [$path, $this->streams->size($path) ?? $take->sizeBytes];
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A video file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
