<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Analytics\AnalyticsReportProvider;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Analytics\AnalyticsTable;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Job\JobDirectory;

/**
 * Funnel and speed: how many applications entered each stage and went on, how long they stayed (average from the daily summary, median read live), how long it took
 * to reach an offer and a hire, and how many offers were accepted. A company also sees its jobs and its recruiters.
 */
final readonly class FunnelReport implements AnalyticsReportProvider
{
    /** The main line of the pipeline; an application can skip or revisit a stage, so a rate is a rate of moves in the range. */
    private const array LINE = ['applied', 'screening', 'interview', 'offer', 'hired'];

    private const int JOB_ROWS = 50;

    public function __construct(
        private ConnectionInterface $connection,
        private DailySummaries $summaries,
        private LiveMedians $medians,
        private JobDirectory $jobs,
        private CompanyMemberAccounts $members,
    ) {}

    public function key(): string
    {
        return 'funnel';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Company];
    }

    public function build(AnalyticsScope $scope): array
    {
        $entered = ['applied' => (int) $this->summaries->applications($scope)->sum('applications')];
        $left = [];
        $durations = $this->medians->of($scope);

        foreach ($this->summaries->transitions($scope)->groupBy('to_type')->get(['to_type', $this->connection->raw('SUM(transitions) as total')]) as $row) {
            $entered[(string) $row->to_type] = (int) $row->total;
        }

        foreach ($this->summaries->transitions($scope)->groupBy('from_type')
            ->get(['from_type', $this->connection->raw('SUM(transitions) as total'), $this->connection->raw('SUM(seconds_in_stage) as seconds')]) as $row) {
            $left[(string) $row->from_type] = ['count' => (int) $row->total, 'seconds' => (int) $row->seconds];
        }

        $stages = [];

        foreach (self::LINE as $index => $stage) {
            $next = self::LINE[$index + 1] ?? null;

            $stages[] = [
                'stage' => $stage,
                'entered' => $entered[$stage] ?? 0,
                'average_days_in_stage' => Figures::days($left[$stage]['seconds'] ?? 0, $left[$stage]['count'] ?? 0),
                'median_days_in_stage' => $durations['in_stage'][$stage] ?? null,
                'next_stage' => $next,
                'conversion' => $next === null ? null : Figures::ratio($entered[$next] ?? 0, $entered[$stage] ?? 0),
            ];
        }

        $tables = [
            new AnalyticsTable('stages', ['stage', 'next_stage'], ['entered', 'average_days_in_stage', 'median_days_in_stage', 'conversion'], $stages, 'entered'),
            new AnalyticsTable('speed', ['milestone'], ['count', 'average_days', 'median_days'], $this->speed($scope, $durations['since_applied']), 'count'),
            new AnalyticsTable('offers', [], ['offers_sent', 'accepted', 'acceptance_rate'], [$this->offers($scope, $entered['offer'] ?? 0)], 'offers_sent'),
        ];

        if ($scope->companyId !== null) {
            $tables[] = $this->byJob($scope);
            $tables[] = $this->byRecruiter($scope);
        }

        return $tables;
    }

    /**
     * @param  array<string, float|null>  $medians
     * @return list<array<string, mixed>>
     */
    private function speed(AnalyticsScope $scope, array $medians): array
    {
        $rows = [];

        foreach (['offer', 'hired'] as $milestone) {
            $row = $this->summaries->transitions($scope)->where('to_type', $milestone)
                ->first([$this->connection->raw('COALESCE(SUM(transitions), 0) as total'), $this->connection->raw('COALESCE(SUM(seconds_since_applied), 0) as seconds')]);

            $rows[] = [
                'milestone' => $milestone,
                'count' => (int) $row->total,
                'average_days' => Figures::days((int) $row->seconds, (int) $row->total),
                'median_days' => $medians[$milestone] ?? null,
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function offers(AnalyticsScope $scope, int $sent): array
    {
        $accepted = (int) $this->summaries->transitions($scope)->where('from_type', 'offer')->where('to_type', 'hired')->sum('transitions');

        return ['offers_sent' => $sent, 'accepted' => $accepted, 'acceptance_rate' => Figures::ratio($accepted, $sent)];
    }

    private function byJob(AnalyticsScope $scope): AnalyticsTable
    {
        $jobs = [];

        foreach ($this->summaries->applications($scope)->groupBy('job_id')->get(['job_id', $this->connection->raw('SUM(applications) as total')]) as $row) {
            $jobs[(int) $row->job_id] = ['job_id' => (int) $row->job_id, 'title' => null, 'applications' => (int) $row->total, 'offers' => 0, 'hired' => 0, 'rejected' => 0, 'hire_rate' => null];
        }

        foreach ($this->summaries->transitions($scope)->whereIn('to_type', ['offer', 'hired', 'rejected'])->groupBy('job_id', 'to_type')
            ->get(['job_id', 'to_type', $this->connection->raw('SUM(transitions) as total')]) as $row) {
            $jobId = (int) $row->job_id;
            $jobs[$jobId] ??= ['job_id' => $jobId, 'title' => null, 'applications' => 0, 'offers' => 0, 'hired' => 0, 'rejected' => 0, 'hire_rate' => null];
            $column = ['offer' => 'offers', 'hired' => 'hired', 'rejected' => 'rejected'][(string) $row->to_type];
            $jobs[$jobId][$column] = (int) $row->total;
        }

        usort($jobs, fn (array $a, array $b): int => [$b['applications'], $a['job_id']] <=> [$a['applications'], $b['job_id']]);
        $jobs = array_slice($jobs, 0, self::JOB_ROWS);
        $titles = $this->jobs->titles(array_column($jobs, 'job_id'));

        foreach ($jobs as &$job) {
            $job['title'] = $titles[$job['job_id']] ?? null;
            $job['hire_rate'] = Figures::ratio($job['hired'], $job['applications']);
        }

        unset($job);

        return new AnalyticsTable('by_job', ['job_id', 'title'], ['applications', 'offers', 'hired', 'rejected', 'hire_rate'], $jobs, 'applications');
    }

    private function byRecruiter(AnalyticsScope $scope): AnalyticsTable
    {
        $members = [];

        foreach ($this->summaries->transitions($scope)->where('actor_id', '>', 0)->groupBy('actor_id')->get([
            'actor_id',
            $this->connection->raw('SUM(transitions) as moves'),
            $this->connection->raw("SUM(CASE WHEN to_type IN ('screening', 'interview', 'offer', 'hired') THEN transitions ELSE 0 END) as advanced"),
            $this->connection->raw("SUM(CASE WHEN to_type = 'rejected' THEN transitions ELSE 0 END) as rejected"),
            $this->connection->raw('SUM(seconds_in_stage) as seconds'),
        ]) as $row) {
            $members[(int) $row->actor_id] = [
                'member_id' => (int) $row->actor_id,
                'name' => null,
                'moves' => (int) $row->moves,
                'advanced' => (int) $row->advanced,
                'rejected' => (int) $row->rejected,
                'average_days_in_stage' => Figures::days((int) $row->seconds, (int) $row->moves),
            ];
        }

        $summaries = $this->members->summaries(array_keys($members));

        foreach ($members as $id => &$member) {
            $member['name'] = isset($summaries[$id]) ? $summaries[$id]->name : null;
        }

        unset($member);

        $members = array_values($members);
        usort($members, fn (array $a, array $b): int => [$b['moves'], $a['member_id']] <=> [$a['moves'], $b['member_id']]);

        return new AnalyticsTable('by_recruiter', ['member_id', 'name'], ['moves', 'advanced', 'rejected', 'average_days_in_stage'], $members, 'moves');
    }
}
