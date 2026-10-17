<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ReviewRequestStatus;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** The open review requests the member made whose due day has passed (Jakarta time). */
final readonly class ReviewsOverdueTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'reviews_overdue';
    }

    public function order(): int
    {
        return 34;
    }

    public function permission(): string
    {
        return CompanyPermission::ApplicationsManage->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/review-requests?filter[role]=requester&filter[state]=overdue';
    }

    public function count(TaskScope $scope): int
    {
        return $this->connection->table('recruitment_review_requests')
            ->where('company_id', $scope->companyId)->where('requested_by_user_id', $scope->userId)
            ->where('status', ReviewRequestStatus::Open->value)
            ->where('due_on', '<', $scope->now->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d'))
            ->count();
    }
}
