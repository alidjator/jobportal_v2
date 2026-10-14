<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsReportProvider;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Analytics\AnalyticsTable;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\References\ReferenceValues;

/**
 * Time to fill: how many days the jobs filled in the range took from going live to their last hire (`TimeToFill`), in total, by category, by company (platform) and, for
 * a company, by job and by recruiter, plus its live jobs that are not filled yet. The figures describe jobs, not people, so a row is never hidden; a median over fewer
 * jobs than the smallest group is null.
 */
final readonly class FillReport implements AnalyticsReportProvider
{
    private const string LANGUAGE = 'en';

    private const int COMPANY_ROWS = 100;

    public function __construct(
        private TimeToFill $fill,
        private ReferenceValues $references,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $members,
        private Clock $clock,
    ) {}

    public function key(): string
    {
        return 'fill';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Company];
    }

    public function build(AnalyticsScope $scope): array
    {
        $filled = $this->fill->filled($scope->companyId, AnalyticsDays::startUtc($scope->from), AnalyticsDays::endUtc($scope->to));
        $figures = ['filled_jobs', 'average_days', 'median_days'];

        $tables = [
            new AnalyticsTable('summary', [], $figures, [$this->figures($filled, $scope->minGroup)]),
            $this->byCategory($filled, $scope->minGroup, $figures),
        ];

        if ($scope->companyId === null) {
            $tables[] = $this->byCompany($filled, $scope->minGroup, $figures);

            return $tables;
        }

        $tables[] = $this->byJob($filled);
        $tables[] = $this->byRecruiter($filled, $scope->minGroup, $figures);
        $tables[] = $this->open($scope->companyId);

        return $tables;
    }

    /**
     * @param  list<FilledJob>  $filled
     * @return array<string, mixed>
     */
    private function figures(array $filled, int $minGroup): array
    {
        $days = array_map(static fn (FilledJob $job): float => $job->days, $filled);

        return ['filled_jobs' => count($filled), 'average_days' => Figures::mean($days), 'median_days' => Figures::median($days, $minGroup)];
    }

    /**
     * @param  list<FilledJob>  $filled
     * @param  list<string>  $figures
     */
    private function byCategory(array $filled, int $minGroup, array $figures): AnalyticsTable
    {
        $groups = [];

        foreach ($filled as $job) {
            $groups[(int) $job->job->categoryId][] = $job;
        }

        $names = $this->references->names('job-categories', array_values(array_filter(array_keys($groups))), self::LANGUAGE);
        $rows = [];

        foreach ($groups as $id => $jobs) {
            $rows[] = ['category_id' => $id === 0 ? null : $id, 'category' => $names[$id] ?? null] + $this->figures($jobs, $minGroup);
        }

        return new AnalyticsTable('by_category', ['category_id', 'category'], $figures, self::ranked($rows));
    }

    /**
     * @param  list<FilledJob>  $filled
     * @param  list<string>  $figures
     */
    private function byCompany(array $filled, int $minGroup, array $figures): AnalyticsTable
    {
        $groups = [];

        foreach ($filled as $job) {
            $groups[$job->job->companyId][] = $job;
        }

        $rows = [];

        foreach ($groups as $id => $jobs) {
            $rows[] = ['company_id' => $id, 'company' => null] + $this->figures($jobs, $minGroup);
        }

        $rows = array_slice(self::ranked($rows), 0, self::COMPANY_ROWS);
        $summaries = $this->companies->summaries(array_column($rows, 'company_id'));

        foreach ($rows as &$row) {
            $row['company'] = isset($summaries[$row['company_id']]) ? $summaries[$row['company_id']]->name : null;
        }

        unset($row);

        return new AnalyticsTable('by_company', ['company_id', 'company'], $figures, $rows);
    }

    /** @param list<FilledJob> $filled */
    private function byJob(array $filled): AnalyticsTable
    {
        $rows = [];

        foreach (array_reverse($filled) as $job) {
            $rows[] = [
                'job_id' => $job->job->id,
                'title' => $job->job->title,
                'published' => AnalyticsDays::dayOf($job->job->publishedAt->format('Y-m-d H:i:s')),
                'filled' => AnalyticsDays::dayOf($job->filledAt->format('Y-m-d H:i:s')),
                'days' => $job->days,
                'vacancies' => $job->job->vacancies,
                'hires' => $job->hires,
            ];
        }

        return new AnalyticsTable('by_job', ['job_id', 'title'], ['published', 'filled', 'days', 'vacancies', 'hires'], $rows);
    }

    /**
     * @param  list<FilledJob>  $filled
     * @param  list<string>  $figures
     */
    private function byRecruiter(array $filled, int $minGroup, array $figures): AnalyticsTable
    {
        $groups = [];

        foreach ($filled as $job) {
            foreach ($job->job->assigneeUserIds as $userId) {
                $groups[$userId][] = $job;
            }
        }

        $summaries = $this->members->summaries(array_keys($groups));
        $rows = [];

        foreach ($groups as $id => $jobs) {
            $rows[] = ['member_id' => $id, 'name' => isset($summaries[$id]) ? $summaries[$id]->name : null] + $this->figures($jobs, $minGroup);
        }

        return new AnalyticsTable('by_recruiter', ['member_id', 'name'], $figures, self::ranked($rows));
    }

    private function open(int $companyId): AnalyticsTable
    {
        $rows = [];

        foreach ($this->fill->open($companyId, $this->clock->now()) as $open) {
            $rows[] = [
                'job_id' => $open->job->id,
                'title' => $open->job->title,
                'published' => AnalyticsDays::dayOf($open->job->publishedAt->format('Y-m-d H:i:s')),
                'days_open' => $open->daysOpen,
                'vacancies' => $open->job->vacancies,
                'hires' => $open->hires,
            ];
        }

        return new AnalyticsTable('open', ['job_id', 'title'], ['published', 'days_open', 'vacancies', 'hires'], $rows);
    }

    /**
     * Most filled jobs first.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function ranked(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => $b['filled_jobs'] <=> $a['filled_jobs']);

        return $rows;
    }
}
