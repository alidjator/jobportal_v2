<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\AnswerRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A candidate's drawing, handed out only to a psychologist of the provider that owns the battery. */
final readonly class GetReviewDrawing
{
    public function __construct(private ReviewAccess $access, private AnswerRepository $answers, private FileStorage $files) {}

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

        return new DrawingFile($contents, $drawing->mimeType);
    }
}
