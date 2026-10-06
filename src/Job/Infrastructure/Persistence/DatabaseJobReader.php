<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Job\Application\JobListItem;
use Src\Job\Application\JobListQuery;
use Src\Job\Application\JobListSort;
use Src\Job\Application\JobReader;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseJobReader implements JobReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function page(JobListQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('job_jobs')->where('job_jobs.company_id', $query->companyId);
        $this->applyFilters($filtered, $query);

        $total = (clone $filtered)->count();

        $assignees = $this->connection->table('job_assignments')
            ->selectRaw('count(*)')
            ->whereColumn('job_assignments.job_id', 'job_jobs.id');

        [$column, $direction] = match ($query->sort) {
            JobListSort::UpdatedAtAscending => ['job_jobs.updated_at', 'asc'],
            JobListSort::UpdatedAtDescending => ['job_jobs.updated_at', 'desc'],
            JobListSort::CreatedAtAscending => ['job_jobs.created_at', 'asc'],
            JobListSort::CreatedAtDescending => ['job_jobs.created_at', 'desc'],
            JobListSort::TitleAscending => ['job_jobs.title', 'asc'],
            JobListSort::TitleDescending => ['job_jobs.title', 'desc'],
            JobListSort::DeadlineAscending => ['job_jobs.deadline', 'asc'],
            JobListSort::DeadlineDescending => ['job_jobs.deadline', 'desc'],
        };

        $rows = $filtered
            ->select(['job_jobs.id', 'job_jobs.title', 'job_jobs.status', 'job_jobs.deadline', 'job_jobs.vacancies', 'job_jobs.published_at', 'job_jobs.created_at', 'job_jobs.updated_at'])
            ->selectSub($assignees, 'assignees_count')
            ->orderBy($column, $direction)
            ->orderBy('job_jobs.id', $direction)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->all();

        return new Page(array_map(fn (stdClass $row): JobListItem => $this->toItem($row), $rows), $total, $request);
    }

    private function applyFilters(Builder $builder, JobListQuery $query): void
    {
        if ($query->assignedToUserId !== null) {
            $builder->whereExists(fn (Builder $assigned) => $assigned
                ->selectRaw('1')
                ->from('job_assignments')
                ->whereColumn('job_assignments.job_id', 'job_jobs.id')
                ->where('job_assignments.user_id', $query->assignedToUserId));
        }

        if ($query->status instanceof JobStatus) {
            $builder->where('job_jobs.status', $query->status->value);
        }

        if ($query->search !== null && $query->search !== '') {
            // The title column uses a case-insensitive collation; `%`, `_` and `\` typed by the member are searched for literally.
            $builder->where('job_jobs.title', 'like', '%'.addcslashes($query->search, '%_\\').'%');
        }
    }

    private function toItem(stdClass $row): JobListItem
    {
        return new JobListItem(
            (int) $row->id,
            (string) $row->title,
            JobStatus::from((string) $row->status),
            $row->deadline === null ? null : $this->time((string) $row->deadline),
            $row->vacancies === null ? null : (int) $row->vacancies,
            (int) $row->assignees_count,
            $row->published_at === null ? null : $this->time((string) $row->published_at),
            $this->time((string) $row->created_at),
            $this->time((string) $row->updated_at),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
