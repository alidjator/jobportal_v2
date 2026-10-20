<?php

namespace Src\Review\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Review\Application\ReviewPermissions;
use Src\Review\Domain\ReplyStatus;
use Src\Review\Domain\ReportStatus;
use Src\Review\Domain\ReviewStatus;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The moderator's queues: reviews and replies that wait to be published and open reports on them (`docs/design/phase-8-admin-queue-cards.md`). Counts only, never text. Needs `reviews.moderate`. */
final readonly class ReviewsQueueCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'reviews_queue';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): string
    {
        return ReviewPermissions::MODERATE;
    }

    public function build(DashboardScope $scope): array
    {
        return [
            'pending_reviews' => $this->connection->table('review_reviews')->where('status', ReviewStatus::Pending->value)->count(),
            'pending_replies' => $this->connection->table('review_replies')->where('status', ReplyStatus::Pending->value)->count(),
            'open_reports' => $this->connection->table('review_reports')->where('status', ReportStatus::Open->value)->count(),
        ];
    }
}
