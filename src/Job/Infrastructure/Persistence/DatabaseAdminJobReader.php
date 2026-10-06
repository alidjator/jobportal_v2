<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Job\Application\Admin\AdminJobListItem;
use Src\Job\Application\Admin\AdminJobQuery;
use Src\Job\Application\Admin\AdminJobReader;
use Src\Job\Application\Admin\AdminJobSort;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseAdminJobReader implements AdminJobReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function page(AdminJobQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('job_jobs');
        $this->applyFilters($filtered, $query);

        $total = (clone $filtered)->count();

        $assignees = $this->connection->table('job_assignments')
            ->selectRaw('count(*)')
            ->whereColumn('job_assignments.job_id', 'job_jobs.id');

        [$column, $direction] = match ($query->sort) {
            AdminJobSort::UpdatedAtAscending => ['job_jobs.updated_at', 'asc'],
            AdminJobSort::UpdatedAtDescending => ['job_jobs.updated_at', 'desc'],
            AdminJobSort::CreatedAtAscending => ['job_jobs.created_at', 'asc'],
            AdminJobSort::CreatedAtDescending => ['job_jobs.created_at', 'desc'],
            AdminJobSort::PublishedAtAscending => ['job_jobs.published_at', 'asc'],
            AdminJobSort::PublishedAtDescending => ['job_jobs.published_at', 'desc'],
            AdminJobSort::TitleAscending => ['job_jobs.title', 'asc'],
            AdminJobSort::TitleDescending => ['job_jobs.title', 'desc'],
            AdminJobSort::DeadlineAscending => ['job_jobs.deadline', 'asc'],
            AdminJobSort::DeadlineDescending => ['job_jobs.deadline', 'desc'],
        };

        $rows = $filtered
            ->select([
                'job_jobs.id', 'job_jobs.title', 'job_jobs.status', 'job_jobs.company_id', 'job_jobs.deadline', 'job_jobs.vacancies', 'job_jobs.published_at',
                'job_jobs.created_at', 'job_jobs.updated_at',
            ])
            ->selectSub($assignees, 'assignees_count')
            ->orderBy($column, $direction)
            ->orderBy('job_jobs.id', $direction)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->all();

        return new Page(array_map(fn (stdClass $row): AdminJobListItem => $this->toItem($row), $rows), $total, $request);
    }

    private function applyFilters(Builder $builder, AdminJobQuery $query): void
    {
        if ($query->status instanceof JobStatus) {
            $builder->where('job_jobs.status', $query->status->value);
        }

        if ($query->companyId !== null) {
            $builder->where('job_jobs.company_id', $query->companyId);
        }

        if ($query->search !== null && $query->search !== '') {
            // The title column uses a case-insensitive collation; `%`, `_` and `\` typed by the admin are searched for literally.
            $like = '%'.addcslashes($query->search, '%_\\').'%';

            $builder->where(function (Builder $match) use ($like, $query): void {
                $match->where('job_jobs.title', 'like', $like);

                if ($query->searchCompanyIds !== []) {
                    $match->orWhereIn('job_jobs.company_id', $query->searchCompanyIds);
                }
            });
        }
    }

    private function toItem(stdClass $row): AdminJobListItem
    {
        return new AdminJobListItem(
            (int) $row->id,
            (string) $row->title,
            JobStatus::from((string) $row->status),
            (int) $row->company_id,
            null,
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
