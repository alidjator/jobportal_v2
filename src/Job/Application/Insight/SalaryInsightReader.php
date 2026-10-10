<?php

namespace Src\Job\Application\Insight;

use DateTimeImmutable;

interface SalaryInsightReader
{
    /**
     * The figures of a group, or null when it has fewer than `$minJobs` jobs or fewer than `$minCompanies` different companies. Only jobs that show a monthly
     * salary (`$monthlyPeriodId`), are `active`, `closed` or `expired`, were published on or after `$since` and do not belong to an excluded company count.
     *
     * @param  list<int>  $excludedCompanyIds
     */
    public function figures(SalaryGroup $group, DateTimeImmutable $since, int $monthlyPeriodId, int $minJobs, int $minCompanies, array $excludedCompanyIds): ?SalaryFigures;
}
