<?php

namespace Src\Job\Infrastructure\Insight;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Application\Insight\SalaryFigures;
use Src\Job\Application\Insight\SalaryGroup;
use Src\Job\Application\Insight\SalaryInsightReader;
use Src\Job\Domain\JobStatus;

/**
 * One query per group: the midpoint of each job's salary range (the average of both ends, or the one end that is set), then the count, the number of
 * companies and `percentile_cont` over those midpoints. Table and column names are fixed here; every value is a bound parameter.
 */
final readonly class DatabaseSalaryInsightReader implements SalaryInsightReader
{
    private const array COUNTED = [JobStatus::Active, JobStatus::Closed, JobStatus::Expired];

    public function __construct(private ConnectionInterface $connection) {}

    public function figures(SalaryGroup $group, DateTimeImmutable $since, int $monthlyPeriodId, int $minJobs, int $minCompanies, array $excludedCompanyIds): ?SalaryFigures
    {
        $jobs = $this->connection->table('job_jobs')
            ->whereIn('status', array_map(static fn (JobStatus $status): string => $status->value, self::COUNTED))
            ->where('salary_visible', true)
            ->where('salary_period_id', $monthlyPeriodId)
            ->where(static fn ($query) => $query->whereNotNull('salary_min')->orWhereNotNull('salary_max'))
            ->where('published_at', '>=', $since->format('Y-m-d H:i:s'));

        foreach (['role_id' => $group->roleId, 'category_id' => $group->categoryId, 'province_id' => $group->provinceId, 'city_id' => $group->cityId, 'experience_level_id' => $group->experienceLevelId] as $column => $value) {
            if ($value !== null) {
                $jobs->where($column, $value);
            }
        }

        if ($excludedCompanyIds !== []) {
            $jobs->whereNotIn('company_id', $excludedCompanyIds);
        }

        $jobs->selectRaw('company_id, COALESCE((salary_min + salary_max) / 2.0, salary_min, salary_max) AS midpoint');

        $row = $jobs->newQuery()->fromSub($jobs, 'salary_rows')->selectRaw(
            'COUNT(*) AS jobs, COUNT(DISTINCT company_id) AS companies, '
            .'percentile_cont(0.25) WITHIN GROUP (ORDER BY midpoint) AS p25, '
            .'percentile_cont(0.5) WITHIN GROUP (ORDER BY midpoint) AS median, '
            .'percentile_cont(0.75) WITHIN GROUP (ORDER BY midpoint) AS p75',
        )->first();

        if ($row === null || (int) $row->jobs < $minJobs || (int) $row->companies < $minCompanies) {
            return null;
        }

        return new SalaryFigures(
            (int) $row->jobs,
            (int) $row->companies,
            (int) round((float) $row->p25),
            (int) round((float) $row->median),
            (int) round((float) $row->p75),
            new DateTimeImmutable,
        );
    }
}
