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
use Src\Shared\Infrastructure\Persistence\NullOrder;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

final readonly class DatabaseJobReader implements JobReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function page(JobListQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('job_jobs')->where('job_jobs.company_id', $query->companyId);
        $this->applyFilters($filtered, $query);

        $total = (clone $filtered)->count();

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

        $rows = $this->withListColumns($filtered)
            ->orderByRaw(NullOrder::sql($column, $direction))
            ->orderBy('job_jobs.id', $direction)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->all();

        return new Page(array_map(fn (stdClass $row): JobListItem => $this->toItem($row), $rows), $total, $request);
    }

    public function hiringManagerBoundaries(int $companyId): array
    {
        $boundaries = [];

        foreach ($this->connection->table('job_jobs')->where('company_id', $companyId)->whereNotNull('hiring_manager_from_stage_id')->get(['id', 'hiring_manager_from_stage_id']) as $row) {
            $boundaries[(int) $row->id] = (int) $row->hiring_manager_from_stage_id;
        }

        return $boundaries;
    }

    public function assignedTo(int $companyId, int $userId): array
    {
        $assigned = $this->connection->table('job_jobs')->where('job_jobs.company_id', $companyId);
        $this->applyFilters($assigned, new JobListQuery($companyId, assignedToUserId: $userId));

        $rows = $this->withListColumns($assigned)
            ->orderByDesc('job_jobs.created_at')
            ->orderByDesc('job_jobs.id')
            ->get()
            ->all();

        return array_map(fn (stdClass $row): JobListItem => $this->toItem($row), $rows);
    }

    /** The columns of a list item, with the number of assignees. */
    private function withListColumns(Builder $builder): Builder
    {
        $assignees = $this->connection->table('job_assignments')
            ->selectRaw('count(*)')
            ->whereColumn('job_assignments.job_id', 'job_jobs.id');

        return $builder
            ->select(['job_jobs.id', 'job_jobs.title', 'job_jobs.status', 'job_jobs.deadline', 'job_jobs.vacancies', 'job_jobs.published_at', 'job_jobs.created_at', 'job_jobs.updated_at'])
            ->selectSub($assignees, 'assignees_count');
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
            TextSearch::contains($builder, 'job_jobs.title', $query->search);
        }

        if ($query->deadlineTo !== null) {
            $builder->whereNotNull('job_jobs.deadline')->where('job_jobs.deadline', '<=', $query->deadlineTo->format('Y-m-d'));
        }

        if ($query->withoutApplications) {
            $builder->whereNotExists(fn (Builder $applied) => $applied->selectRaw('1')->from('recruitment_applications')->whereColumn('recruitment_applications.job_id', 'job_jobs.id'));
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
