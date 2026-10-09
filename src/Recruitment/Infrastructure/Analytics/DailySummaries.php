<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Shared\Application\Analytics\AnalyticsScope;

/** The two daily summaries of Recruitment, limited to the days and the company of a scope. */
final readonly class DailySummaries
{
    public function __construct(private ConnectionInterface $connection) {}

    public function applications(AnalyticsScope $scope): Builder
    {
        return $this->limited($this->connection->table('recruitment_applications_daily'), $scope);
    }

    public function transitions(AnalyticsScope $scope): Builder
    {
        return $this->limited($this->connection->table('recruitment_transitions_daily'), $scope);
    }

    private function limited(Builder $query, AnalyticsScope $scope): Builder
    {
        return $query
            ->where('day', '>=', $scope->from)
            ->where('day', '<=', $scope->to)
            ->when($scope->companyId !== null, fn (Builder $query) => $query->where('company_id', $scope->companyId));
    }
}
