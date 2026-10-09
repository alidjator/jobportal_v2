<?php

namespace Src\Job\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Shared\Application\Analytics\AnalyticsReportProvider;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Analytics\AnalyticsTable;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/**
 * The job market: jobs that went live and applications received by category, province and experience level, applications per job, and the salary range of the jobs
 * that show one, per salary period. The salary rows show only when enough jobs stand behind them.
 */
final readonly class MarketReport implements AnalyticsReportProvider
{
    private const string LANGUAGE = 'en';

    public function __construct(
        private ConnectionInterface $connection,
        private ReferenceValues $references,
        private LocationReferences $locations,
    ) {}

    public function key(): string
    {
        return 'market';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Company];
    }

    public function build(AnalyticsScope $scope): array
    {
        $categories = $this->grouped($scope, 'category_id');
        $provinces = $this->grouped($scope, 'province_id');
        $levels = $this->grouped($scope, 'experience_level_id');

        $categoryNames = $this->references->names('job-categories', $this->ids($categories, 'category_id'), self::LANGUAGE);
        $provinceNames = $this->locations->provinceNames($this->ids($provinces, 'province_id'));
        $levelNames = $this->references->names('experience-levels', $this->ids($levels, 'experience_level_id'), self::LANGUAGE);

        return [
            $this->table('categories', 'category_id', $categories, $categoryNames),
            $this->table('provinces', 'province_id', $provinces, $provinceNames),
            $this->table('experience_levels', 'experience_level_id', $levels, $levelNames),
            $this->salary($scope),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function grouped(AnalyticsScope $scope, string $column): array
    {
        $rows = [];

        foreach ($this->limited($scope)->groupBy($column)->get([
            $column,
            $this->connection->raw('SUM(jobs) as jobs'),
            $this->connection->raw('SUM(applications) as applications'),
        ]) as $row) {
            $rows[] = [$column => (int) $row->{$column}, 'jobs' => (int) $row->jobs, 'applicants' => (int) $row->applications];
        }

        usort($rows, fn (array $a, array $b): int => [$b['jobs'], $a[$column]] <=> [$a['jobs'], $b[$column]]);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $names
     */
    private function table(string $name, string $column, array $rows, array $names): AnalyticsTable
    {
        foreach ($rows as &$row) {
            $id = $row[$column];
            $row = [$column => $id, 'name' => $id === 0 ? null : ($names[$id] ?? null)] + $row;
            $row['applicants_per_job'] = $row['jobs'] === 0 ? null : round($row['applicants'] / $row['jobs'], 2);
        }

        unset($row);

        return new AnalyticsTable($name, [$column, 'name'], ['jobs', 'applicants', 'applicants_per_job'], $rows, 'applicants', ['applicants', 'applicants_per_job']);
    }

    private function salary(AnalyticsScope $scope): AnalyticsTable
    {
        $rows = [];

        foreach ($this->limited($scope)->where('salary_period_id', '>', 0)->groupBy('salary_period_id')->get([
            'salary_period_id',
            $this->connection->raw('SUM(jobs) as jobs'),
            $this->connection->raw('SUM(salary_min_sum) as lower'),
            $this->connection->raw('SUM(salary_max_sum) as upper'),
            $this->connection->raw('MIN(salary_lowest) as lowest'),
            $this->connection->raw('MAX(salary_highest) as highest'),
        ]) as $row) {
            $jobs = (int) $row->jobs;
            $rows[(int) $row->salary_period_id] = [
                'salary_period_id' => (int) $row->salary_period_id,
                'name' => null,
                'jobs' => $jobs,
                'average_lower' => $jobs === 0 ? null : (int) round((int) $row->lower / $jobs),
                'average_upper' => $jobs === 0 ? null : (int) round((int) $row->upper / $jobs),
                'lowest' => $row->lowest === null ? null : (int) $row->lowest,
                'highest' => $row->highest === null ? null : (int) $row->highest,
            ];
        }

        $names = $this->references->names('salary-periods', array_keys($rows), self::LANGUAGE);

        foreach ($rows as $id => &$row) {
            $row['name'] = $names[$id] ?? null;
        }

        unset($row);

        return new AnalyticsTable('salary', ['salary_period_id', 'name'], ['jobs', 'average_lower', 'average_upper', 'lowest', 'highest'], array_values($rows), 'jobs');
    }

    private function limited(AnalyticsScope $scope): Builder
    {
        return $this->connection->table('job_market_daily')
            ->where('day', '>=', $scope->from)
            ->where('day', '<=', $scope->to)
            ->when($scope->companyId !== null, fn (Builder $query) => $query->where('company_id', $scope->companyId));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function ids(array $rows, string $column): array
    {
        return array_values(array_filter(array_map(fn (array $row): int => (int) $row[$column], $rows), fn (int $id): bool => $id > 0));
    }
}
