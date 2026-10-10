<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;

/**
 * Runs when a job seeker's account is anonymised. Their published reviews stay with the ratings but lose the title and body (the name already reads `Deleted user`);
 * the reviews that were not published are deleted, and so are the reports they made. Safe to run again.
 */
final readonly class EraseReviewerData
{
    public function __construct(
        private ReviewRepository $reviews,
        private ReportRepository $reports,
        private ReviewSummaries $summaries,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function execute(int $userId): void
    {
        $companies = $this->transactions->run(function () use ($userId): array {
            $this->reports->deleteOfUser($userId);

            return $this->reviews->eraseOfUser($userId, $this->clock->now());
        });

        foreach ($companies as $companyId) {
            $this->summaries->forget($companyId);
        }
    }
}
