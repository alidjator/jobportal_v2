<?php

namespace Src\Job\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsRollup;
use Src\Shared\Application\Recruitment\ApplicationCounts;

/**
 * Jobs that went live on a day and the applications received that day, by company, category, province and experience level, with the salary of the jobs that show one
 * per salary period. A job with no value for a dimension counts under 0.
 */
final readonly class JobMarketDailyRollup implements AnalyticsRollup
{
    public function __construct(private ConnectionInterface $connection, private ApplicationCounts $applications) {}

    public function name(): string
    {
        return 'job_market_daily';
    }

    public function earliestDay(): ?string
    {
        $first = $this->connection->table('job_jobs')->min('published_at');

        return $first === null ? null : AnalyticsDays::dayOf((string) $first);
    }

    public function rebuild(string $day): void
    {
        $buckets = [];

        foreach ($this->connection->table('job_jobs')
            ->where('published_at', '>=', AnalyticsDays::startUtc($day))
            ->where('published_at', '<', AnalyticsDays::endUtc($day))
            ->get($this->columns()) as $job) {
            $bucket = &$this->bucket($buckets, $day, $job);
            $bucket['jobs']++;

            if ($bucket['salary_period_id'] > 0) {
                $lower = (int) ($job->salary_min ?? $job->salary_max);
                $upper = (int) ($job->salary_max ?? $job->salary_min);
                $bucket['salary_min_sum'] += $lower;
                $bucket['salary_max_sum'] += $upper;
                $bucket['salary_lowest'] = $bucket['salary_lowest'] === null ? $lower : min($bucket['salary_lowest'], $lower);
                $bucket['salary_highest'] = $bucket['salary_highest'] === null ? $upper : max($bucket['salary_highest'], $upper);
            }

            unset($bucket);
        }

        $received = $this->applications->onDay($day);

        foreach (array_chunk(array_keys($received), 500) as $ids) {
            foreach ($this->connection->table('job_jobs')->whereIn('id', $ids)->get($this->columns()) as $job) {
                $bucket = &$this->bucket($buckets, $day, $job);
                $bucket['applications'] += $received[(int) $job->id];
                unset($bucket);
            }
        }

        $this->connection->transaction(function () use ($day, $buckets): void {
            $this->connection->table('job_market_daily')->where('day', $day)->delete();

            foreach (array_chunk(array_values($buckets), 500) as $chunk) {
                $this->connection->table('job_market_daily')->insert($chunk);
            }
        });
    }

    /** @return list<string> */
    private function columns(): array
    {
        return ['id', 'company_id', 'category_id', 'province_id', 'experience_level_id', 'salary_visible', 'salary_min', 'salary_max', 'salary_period_id'];
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     * @return array<string, mixed>
     */
    private function &bucket(array &$buckets, string $day, object $job): array
    {
        $showsSalary = (bool) $job->salary_visible && ($job->salary_min !== null || $job->salary_max !== null) && $job->salary_period_id !== null;
        $period = $showsSalary ? (int) $job->salary_period_id : 0;
        $key = implode('|', [$job->company_id, (int) $job->category_id, (int) $job->province_id, (int) $job->experience_level_id, $period]);

        $buckets[$key] ??= [
            'day' => $day,
            'company_id' => (int) $job->company_id,
            'category_id' => (int) $job->category_id,
            'province_id' => (int) $job->province_id,
            'experience_level_id' => (int) $job->experience_level_id,
            'salary_period_id' => $period,
            'jobs' => 0,
            'applications' => 0,
            'salary_min_sum' => 0,
            'salary_max_sum' => 0,
            'salary_lowest' => null,
            'salary_highest' => null,
        ];

        return $buckets[$key];
    }
}
