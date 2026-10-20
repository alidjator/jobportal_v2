<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsReportProvider;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Analytics\AnalyticsTable;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Job\JobDirectory;

/**
 * Funnel and speed: how many applications entered each stage and went on, how long they stayed (average from the daily summary, median read live), how long it took
 * to reach an offer and a hire, how many offers were accepted, and how the interviews that started in the range ended (read live; no-shows of
 * `docs/design/phase-8-interview-reschedule.md` section 3). A company also sees its jobs and its recruiters.
 */
final readonly class FunnelReport implements AnalyticsReportProvider
{
    /** The main line of the pipeline; an application can skip or revisit a stage, so a rate is a rate of moves in the range. */
    private const array LINE = ['applied', 'screening', 'assessment', 'interview', 'offer', 'hired'];

    private const int JOB_ROWS = 50;

    private const array INTERVIEW_STATUSES = ['pending', 'confirmed', 'completed', 'no_show', 'declined', 'cancelled'];

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

            // The assessment stage is optional: while no application entered it in the range, the stage before it is measured against the one after it.
            if ($next === 'assessment' && ($entered['assessment'] ?? 0) === 0) {
                $next = self::LINE[$index + 2] ?? null;
            }

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
            $this->withdrawals($scope),
            $this->interviews($scope),
            $this->imported($scope),
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

    /** The withdrawn applications by reason and by who marked them: the job seeker (or an offer decline) or a team member. A withdrawal without a reason counts as `unknown`. */
    private function withdrawals(AnalyticsScope $scope): AnalyticsTable
    {
        $rows = [];

        foreach ($this->summaries->transitions($scope)->where('to_type', 'withdrawn')->groupBy('rejection_reason')->get([
            'rejection_reason',
            $this->connection->raw('SUM(transitions) as total'),
            $this->connection->raw('SUM(CASE WHEN actor_id > 0 THEN transitions ELSE 0 END) as by_team'),
        ]) as $row) {
            $rows[] = [
                'reason' => $row->rejection_reason === 'none' ? 'unknown' : (string) $row->rejection_reason,
                'count' => (int) $row->total,
                'by_applicant' => (int) $row->total - (int) $row->by_team,
                'by_team' => (int) $row->by_team,
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['count'], $a['reason']] <=> [$a['count'], $b['reason']]);

        return new AnalyticsTable('by_reason', ['reason'], ['count', 'by_applicant', 'by_team'], $rows, 'count');
    }

    /**
     * The interviews that started in the range by status: `pending` was never answered, `confirmed` took place but was not marked. The no-show rate is the share of
     * no-shows among the interviews that were confirmed (`confirmed`, `completed` and `no_show`).
     */
    private function interviews(AnalyticsScope $scope): AnalyticsTable
    {
        $counts = array_fill_keys(self::INTERVIEW_STATUSES, 0);

        foreach ($this->connection->table('recruitment_interviews as i')
            ->join('recruitment_applications as a', 'a.id', '=', 'i.application_id')
            ->when($scope->companyId !== null, fn (Builder $query) => $query->where('a.company_id', $scope->companyId))
            ->where('i.starts_at', '>=', AnalyticsDays::startUtc($scope->from))
            ->where('i.starts_at', '<', AnalyticsDays::endUtc($scope->to))
            ->groupBy('i.status')
            ->get(['i.status', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        $held = $counts['confirmed'] + $counts['completed'] + $counts['no_show'];
        $row = ['scheduled' => array_sum($counts), ...$counts, 'no_show_rate' => Figures::ratio($counts['no_show'], $held)];

        return new AnalyticsTable('interviews', [], ['scheduled', ...self::INTERVIEW_STATUSES, 'no_show_rate'], [$row], 'scheduled');
    }

    /**
     * The applications of the range that came from the karir.pkp import. The applications per day count them, but their stage moves and times are left out of the
     * stages, speed, offers and withdrawals tables (`docs/design/phase-8-imported-history-in-reports.md`), so the page can explain the first months.
     */
    private function imported(AnalyticsScope $scope): AnalyticsTable
    {
        $row = $this->connection->table('recruitment_applications as a')
            ->whereNotNull('a.migrated_at')
            ->where('a.applied_at', '>=', AnalyticsDays::startUtc($scope->from))
            ->where('a.applied_at', '<', AnalyticsDays::endUtc($scope->to))
            ->when($scope->companyId !== null, fn (Builder $query) => $query->where('a.company_id', $scope->companyId))
            ->first([$this->connection->raw('COUNT(*) as total'), $this->connection->raw("COALESCE(SUM(CASE WHEN a.stage_type = 'hired' THEN 1 ELSE 0 END), 0) as hired")]);

        $applications = (int) $row->total;

        return new AnalyticsTable('imported', [], ['includes_imported', 'applications', 'hired'], [['includes_imported' => $applications > 0, 'applications' => $applications, 'hired' => (int) $row->hired]]);
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
            $this->connection->raw("SUM(CASE WHEN to_type IN ('screening', 'assessment', 'interview', 'offer', 'hired') THEN transitions ELSE 0 END) as advanced"),
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
