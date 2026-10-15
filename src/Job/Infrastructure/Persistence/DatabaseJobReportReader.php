<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Application\Reports\JobReportEntry;
use Src\Job\Application\Reports\JobReportReader;
use Src\Job\Application\Reports\ReportedJob;
use Src\Job\Domain\JobReportReason;
use Src\Job\Domain\JobReportStatus;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseJobReportReader implements JobReportReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function reportedJobs(PageRequest $request): Page
    {
        $open = JobReportStatus::Open->value;

        $total = (int) $this->connection->table('job_reports')->where('status', $open)->distinct()->count('job_id');

        $rows = $this->connection->table('job_reports')
            ->join('job_jobs', 'job_jobs.id', '=', 'job_reports.job_id')
            ->where('job_reports.status', $open)
            ->groupBy('job_jobs.id', 'job_jobs.title', 'job_jobs.status', 'job_jobs.company_id', 'job_jobs.report_hold_at')
            ->select(['job_jobs.id', 'job_jobs.title', 'job_jobs.status', 'job_jobs.company_id', 'job_jobs.report_hold_at'])
            ->selectRaw('count(*) as open_count, min(job_reports.created_at) as first_at, max(job_reports.created_at) as last_at')
            ->orderByRaw('(job_jobs.report_hold_at is not null) desc')
            ->orderByRaw('count(*) desc')
            ->orderByRaw('max(job_reports.created_at) desc')
            ->orderBy('job_jobs.id')
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get();

        $reasons = [];

        foreach ($this->connection->table('job_reports')
            ->where('status', $open)->whereIn('job_id', $rows->pluck('id')->all())
            ->groupBy('job_id', 'reason')->orderBy('reason')
            ->get(['job_id', 'reason', $this->connection->raw('count(*) as total')]) as $row) {
            $reasons[(int) $row->job_id][(string) $row->reason] = (int) $row->total;
        }

        return new Page(
            $rows->map(fn (stdClass $row): ReportedJob => new ReportedJob(
                (int) $row->id,
                (string) $row->title,
                JobStatus::from((string) $row->status),
                (int) $row->company_id,
                null,
                (int) $row->open_count,
                $reasons[(int) $row->id] ?? [],
                $this->time((string) $row->first_at),
                $this->time((string) $row->last_at),
                $row->report_hold_at === null ? null : $this->time((string) $row->report_hold_at),
            ))->all(),
            $total,
            $request,
        );
    }

    public function reportsOfJob(int $jobId, PageRequest $request): Page
    {
        $query = $this->connection->table('job_reports')->where('job_id', $jobId);
        $total = (clone $query)->count();

        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(
            $rows->map(fn (stdClass $row): JobReportEntry => new JobReportEntry(
                (int) $row->id,
                JobReportReason::from((string) $row->reason),
                $row->note === null ? null : (string) $row->note,
                JobReportStatus::from((string) $row->status),
                $row->reporter_user_id === null ? null : (int) $row->reporter_user_id,
                $this->time((string) $row->created_at),
                $row->decided_at === null ? null : $this->time((string) $row->decided_at),
                $row->decided_by_admin_id === null ? null : (int) $row->decided_by_admin_id,
            ))->all(),
            $total,
            $request,
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
