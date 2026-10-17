<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Domain\ReviewRequestStatus;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** The applications of open review requests that the member is asked to review and has not answered; an application that became final needs no answer. */
final readonly class MyReviewsTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'my_reviews';
    }

    public function order(): int
    {
        return 33;
    }

    public function permission(): string
    {
        return CompanyPermission::ApplicationsReview->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/review-requests?filter[state]=pending';
    }

    public function count(TaskScope $scope): int
    {
        return $this->connection->table('recruitment_review_request_items as i')
            ->join('recruitment_review_requests as r', 'r.id', '=', 'i.request_id')
            ->join('recruitment_review_request_reviewers as v', fn ($join) => $join->on('v.request_id', '=', 'r.id')->where('v.user_id', $scope->userId))
            ->join('recruitment_applications as a', 'a.id', '=', 'i.application_id')
            ->where('r.company_id', $scope->companyId)
            ->where('r.status', ReviewRequestStatus::Open->value)
            ->whereNotIn('a.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value])
            ->whereNotExists(fn (Builder $answered) => $answered->selectRaw('1')->from('recruitment_application_reviews as w')
                ->whereColumn('w.request_id', 'r.id')->whereColumn('w.application_id', 'i.application_id')->where('w.reviewer_user_id', $scope->userId))
            ->count();
    }
}
