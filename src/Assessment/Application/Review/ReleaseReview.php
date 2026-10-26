<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The holder lets go of a report. Releasing one nobody holds (or whose claim lapsed) changes nothing; one that another psychologist holds is refused. */
final readonly class ReleaseReview
{
    public function __construct(
        private ReviewAccess $access,
        private ReportRepository $reports,
        private ReviewViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `review-claimed`
     */
    public function execute(Actor $actor, int $reportId): ReviewView
    {
        return $this->transactions->run(function () use ($actor, $reportId): ReviewView {
            $psychologist = $this->access->psychologist($actor);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);
            $now = $this->clock->now();

            if ($report->hasLiveClaim($now) && ! $report->isClaimedBy((int) $psychologist->id, $now)) {
                $holder = $this->views->holderOf($psychologist, $report);

                throw new StateConflict('Another psychologist holds this report.', 'review-claimed', extensions: ['holder' => $holder === null ? null : ['id' => $holder['id'], 'name' => $holder['name']]]);
            }

            $released = $report->claimReleased();

            if ($report->claimedByPsychologistId !== null) {
                $this->reports->update($released);
            }

            return $this->views->build($psychologist, $released);
        });
    }
}
