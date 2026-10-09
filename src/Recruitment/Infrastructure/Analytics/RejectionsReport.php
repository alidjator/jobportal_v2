<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Analytics\AnalyticsReportProvider;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Analytics\AnalyticsTable;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Job\JobDirectory;

/**
 * Rejections and sources: why applications were rejected, where applicants came from and how many of each source were hired, and (for a company) the jobs that
 * convert worst. Applications from before the source was recorded count as `unknown`.
 */
final readonly class RejectionsReport implements AnalyticsReportProvider
{
    private const int WORST_JOBS = 10;

    public function __construct(
        private ConnectionInterface $connection,
        private DailySummaries $summaries,
        private JobDirectory $jobs,
    ) {}

    public function key(): string
    {
        return 'rejections';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Company];
    }

    public function build(AnalyticsScope $scope): array
    {
        $tables = [$this->reasons($scope), $this->sources($scope)];

        if ($scope->companyId !== null) {
            $tables[] = $this->worstJobs($scope);
        }

        return $tables;
    }

    private function reasons(AnalyticsScope $scope): AnalyticsTable
    {
        $reasons = [];
        $total = 0;

        foreach ($this->summaries->transitions($scope)->where('to_type', 'rejected')->groupBy('rejection_reason')
            ->get(['rejection_reason', $this->connection->raw('SUM(transitions) as total')]) as $row) {
            $count = (int) $row->total;
            $total += $count;
            $reasons[] = ['reason' => $row->rejection_reason === 'none' ? 'unspecified' : (string) $row->rejection_reason, 'count' => $count, 'share' => null];
        }

        foreach ($reasons as &$reason) {
            $reason['share'] = Figures::ratio($reason['count'], $total);
        }

        unset($reason);

        usort($reasons, fn (array $a, array $b): int => [$b['count'], $a['reason']] <=> [$a['count'], $b['reason']]);

        return new AnalyticsTable('reasons', ['reason'], ['count', 'share'], $reasons, 'count');
    }

    private function sources(AnalyticsScope $scope): AnalyticsTable
    {
        $sources = [];

        foreach ($this->summaries->applications($scope)->groupBy('source')->get(['source', $this->connection->raw('SUM(applications) as total')]) as $row) {
            $sources[(string) $row->source] = ['source' => (string) $row->source, 'applications' => (int) $row->total, 'hired' => 0, 'hire_rate' => null];
        }

        foreach ($this->summaries->transitions($scope)->where('to_type', 'hired')->groupBy('source')->get(['source', $this->connection->raw('SUM(transitions) as total')]) as $row) {
            $source = (string) $row->source;
            $sources[$source] ??= ['source' => $source, 'applications' => 0, 'hired' => 0, 'hire_rate' => null];
            $sources[$source]['hired'] = (int) $row->total;
        }

        foreach ($sources as &$source) {
            $source['hire_rate'] = Figures::ratio($source['hired'], $source['applications']);
        }

        unset($source);

        $rows = array_values($sources);
        usort($rows, fn (array $a, array $b): int => [$b['applications'], $a['source']] <=> [$a['applications'], $b['source']]);

        return new AnalyticsTable('sources', ['source'], ['applications', 'hired', 'hire_rate'], $rows, 'applications');
    }

    /** The jobs with the lowest share of hires among at least as many applications as the smallest group may show. */
    private function worstJobs(AnalyticsScope $scope): AnalyticsTable
    {
        $jobs = [];

        foreach ($this->summaries->applications($scope)->groupBy('job_id')->get(['job_id', $this->connection->raw('SUM(applications) as total')]) as $row) {
            if ((int) $row->total >= $scope->minGroup) {
                $jobs[(int) $row->job_id] = ['job_id' => (int) $row->job_id, 'title' => null, 'applications' => (int) $row->total, 'hired' => 0, 'hire_rate' => 0.0];
            }
        }

        foreach ($this->summaries->transitions($scope)->where('to_type', 'hired')->groupBy('job_id')->get(['job_id', $this->connection->raw('SUM(transitions) as total')]) as $row) {
            if (isset($jobs[(int) $row->job_id])) {
                $jobs[(int) $row->job_id]['hired'] = (int) $row->total;
            }
        }

        foreach ($jobs as &$job) {
            $job['hire_rate'] = Figures::ratio($job['hired'], $job['applications']);
        }

        unset($job);

        $rows = array_values($jobs);
        usort($rows, fn (array $a, array $b): int => [$a['hire_rate'], -$a['applications'], $a['job_id']] <=> [$b['hire_rate'], -$b['applications'], $b['job_id']]);
        $rows = array_slice($rows, 0, self::WORST_JOBS);
        $titles = $this->jobs->titles(array_column($rows, 'job_id'));

        foreach ($rows as &$row) {
            $row['title'] = $titles[$row['job_id']] ?? null;
        }

        unset($row);

        return new AnalyticsTable('worst_jobs', ['job_id', 'title'], ['applications', 'hired', 'hire_rate'], $rows, 'applications');
    }
}
