<?php

namespace Src\Assessment\Application\Review;

use Psr\Log\LoggerInterface;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\DrawingSource;
use Src\Assessment\Domain\QuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageOverlay;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A candidate's drawing, handed out only to a psychologist of the provider that owns the battery. A drawing made on the canvas of a question that has a sheet is stored as the strokes alone; the reader gets them laid
 * over the question's own sheet (`docs/design/phase-8-question-images.md` D12), so the sheet is always the one of the question. A photo the candidate uploaded is handed out as it is.
 */
final readonly class GetReviewDrawing
{
    public function __construct(
        private ReviewAccess $access,
        private AnswerRepository $answers,
        private QuestionRepository $questions,
        private FileStorage $files,
        private ImageOverlay $overlay,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $reportId, int $questionId): DrawingFile
    {
        $psychologist = $this->access->psychologist($actor);
        $report = $this->access->report($psychologist, $reportId);
        $drawing = $this->answers->drawingsOf($report->attemptId)[$questionId] ?? throw EntityNotFound::of('Drawing', $questionId);
        $contents = $this->files->get($drawing->path) ?? throw EntityNotFound::of('Drawing', $questionId);

        if ($drawing->source === DrawingSource::Canvas) {
            return $this->onSheet($contents, $questionId) ?? new DrawingFile($contents, $drawing->mimeType);
        }

        return new DrawingFile($contents, $drawing->mimeType);
    }

    /** The strokes over the sheet of the question; null when the question has no sheet (or it cannot be read), so the strokes are handed out as they are. */
    private function onSheet(string $strokes, int $questionId): ?DrawingFile
    {
        $sheet = ($this->questions->find($questionId)?->images ?? [])[0] ?? null;
        $base = $sheet === null ? null : $this->files->get($sheet->path);

        if ($base === null) {
            return null;
        }

        try {
            $image = $this->overlay->overlay($base, $strokes);
        } catch (BusinessRuleViolation $e) {
            $this->logger->warning('A drawing could not be laid over the sheet of its question.', ['question_id' => $questionId, 'exception' => $e]);

            return null;
        }

        return new DrawingFile($image->contents, $image->mimeType);
    }
}
