<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A psychologist of the provider claims a report, so that nobody else works on it at the same time. A claim lapses after 24 hours without a write by its holder; then anyone may
 * claim. Claiming a report one already holds renews it. Any report can be claimed, a signed one too (to revise it, and to mark an automatic one reviewed); a signed report stays locked for editing.
 */
final readonly class ClaimReview
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
     * @throws StateConflict `review-claimed` (with the holder's name)
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

            $claimed = $report->claimedBy($psychologist, $now);
            $this->reports->update($claimed);

            return $this->views->build($psychologist, $claimed);
        });
    }
}
