<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\ReportMessageAuthor;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The questions a company asked about a report of the psychologist's institution and the answers. */
final readonly class ListProviderReportMessages
{
    public function __construct(private ReviewAccess $access, private ReportThreads $threads) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $reportId): ReportThread
    {
        $psychologist = $this->access->psychologist($actor);
        $report = $this->access->report($psychologist, $reportId);

        return $this->threads->build($report, ReportMessageAuthor::Psychologist, (int) $actor->id);
    }
}
