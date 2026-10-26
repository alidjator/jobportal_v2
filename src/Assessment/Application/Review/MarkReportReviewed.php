<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A psychologist of the provider, with a valid STR and SILP, marks a report that was issued automatically as reviewed. Marking it again changes nothing. */
final readonly class MarkReportReviewed
{
    public function __construct(
        private ReviewAccess $access,
        private ReportRepository $reports,
        private ReviewViews $views,
        private ReviewClaims $claims,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended`, `provider-suspended` or `cannot-sign-reports`
     * @throws EntityNotFound
     * @throws StateConflict `report-not-auto-issued` or `claim-required`
     */
    public function execute(Actor $actor, int $reportId): ReviewView
    {
        return $this->transactions->run(function () use ($actor, $reportId): ReviewView {
            $psychologist = $this->access->psychologist($actor);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);

            if (! $psychologist->canSignReports($this->clock->now())) {
                throw new AccessDenied('Your STR or SILP is not valid today, so you cannot review reports.', 'cannot-sign-reports');
            }

            $reviewed = $report->markedReviewed($psychologist, $this->clock->now());

            // Marking a report that was reviewed already changes nothing, so it needs no claim.
            if ($reviewed !== $report) {
                $this->claims->assertHeld($psychologist, $report);
                $reviewed = $reviewed->claimReleased();
                $this->reports->update($reviewed);
            }

            return $this->views->build($psychologist, $reviewed);
        });
    }
}
