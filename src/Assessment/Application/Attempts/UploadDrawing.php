<?php

namespace Src\Assessment\Application\Attempts;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Drawing;
use Src\Assessment\Domain\DrawingSource;
use Src\Assessment\Domain\QuestionType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * The candidate sends the drawing for a drawing question: what they drew on the canvas or a photo of a drawing. The file is decoded and encoded again (so only
 * the picture remains) and kept under a random name on the private disk. Sending again replaces the earlier drawing until time runs out.
 */
final readonly class UploadDrawing
{
    private const string DIRECTORY = 'assessment/drawings';

    private const int MAX_SIDE = 2400;

    public function __construct(
        private AttemptGate $gate,
        private AttemptQuestions $reach,
        private AnswerRepository $answers,
        private Settings $settings,
        private ImageProcessor $images,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `file-too-large`, `invalid-image` or `invalid-answer`
     * @throws StateConflict `attempt-not-started`, `attempt-closed` or `question-locked`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, DrawingSource $source, string $contents, ?string $ip): void
    {
        $replaced = $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId, $source, $contents): ?string {
            $question = $this->reach->reachable($context, $questionId);

            if ($question->type !== QuestionType::Drawing) {
                throw new BusinessRuleViolation('This question is not answered with a drawing.', 'invalid-answer', extensions: ['errors' => ['question' => ['This question is not answered with a drawing.']]]);
            }

            $maxMb = $this->settings->integer(AssessmentSettings::MAX_DRAWING_MEGABYTES);

            if (strlen($contents) > $maxMb * 1024 * 1024) {
                throw new BusinessRuleViolation("The drawing is larger than the allowed {$maxMb} MB.", 'file-too-large', extensions: ['max_size_mb' => $maxMb]);
            }

            $image = $this->images->process($contents, self::MAX_SIDE, self::MAX_SIDE);
            $attemptId = (int) $context->attempt->id;
            $path = self::DIRECTORY.'/'.$attemptId.'/'.bin2hex(random_bytes(16)).'.'.$image->extension;

            $this->files->put($path, $image->contents);

            try {
                return $this->answers->saveDrawing($attemptId, new Drawing($questionId, $path, $source, $image->mimeType, strlen($image->contents)));
            } catch (Throwable $e) {
                $this->deleteQuietly($path);

                throw $e;
            }
        });

        if ($replaced !== null) {
            $this->deleteQuietly($replaced);
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A drawing file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
