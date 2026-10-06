<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Job\Application\Browse\JobSearchHit;
use Src\Job\Application\Browse\JobSearchQuery;
use Src\Job\Application\Browse\JobSearchReader;
use Src\Job\Application\Browse\JobSearchSort;
use Src\Job\Application\Browse\SearchWord;
use Src\Job\Domain\JobStatus;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

/**
 * Only live jobs are searched: `active`, deadline not before today (Jakarta time), company not in the excluded list. Table and column names are fixed in this
 * class (never taken from input); every value is a bound parameter. The title column uses a case-insensitive collation, so the keyword needs no lower-casing.
 */
final readonly class DatabaseJobSearchReader implements JobSearchReader
{
    /** Criteria property => column of `job_jobs`. */
    private const array ID_FILTERS = [
        'categoryIds' => 'category_id',
        'roleIds' => 'role_id',
        'jobTypeIds' => 'job_type_id',
        'jobLevelIds' => 'job_level_id',
        'experienceLevelIds' => 'experience_level_id',
        'educationLevelIds' => 'education_level_id',
        'workTimeIds' => 'work_time_id',
        'provinceIds' => 'province_id',
        'cityIds' => 'city_id',
        'companyIds' => 'company_id',
    ];

    public function __construct(private ConnectionInterface $connection) {}

    public function page(JobSearchQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('job_jobs')
            ->where('job_jobs.status', JobStatus::Active->value)
            ->where('job_jobs.deadline', '>=', $query->today->format('Y-m-d'));

        if ($query->excludedCompanyIds !== []) {
            $filtered->whereNotIn('job_jobs.company_id', $query->excludedCompanyIds);
        }

        $this->applyFilters($filtered, $query);

        $total = (clone $filtered)->count();

        [$column, $direction] = match ($query->criteria->sort) {
            JobSearchSort::PublishedAtDescending => ['job_jobs.published_at', 'desc'],
            JobSearchSort::PublishedAtAscending => ['job_jobs.published_at', 'asc'],
            JobSearchSort::DeadlineAscending => ['job_jobs.deadline', 'asc'],
            JobSearchSort::DeadlineDescending => ['job_jobs.deadline', 'desc'],
        };

        $rows = $filtered
            ->select([
                'job_jobs.id', 'job_jobs.slug', 'job_jobs.title', 'job_jobs.company_id', 'job_jobs.province_id', 'job_jobs.city_id', 'job_jobs.job_type_id',
                'job_jobs.job_level_id', 'job_jobs.work_mode', 'job_jobs.salary_min', 'job_jobs.salary_max', 'job_jobs.salary_period_id', 'job_jobs.salary_visible',
                'job_jobs.deadline', 'job_jobs.published_at',
            ])
            ->orderBy($column, $direction)
            ->orderBy('job_jobs.id', $direction)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->all();

        return new Page(array_map(fn (stdClass $row): JobSearchHit => $this->toHit($row), $rows), $total, $request);
    }

    private function applyFilters(Builder $builder, JobSearchQuery $query): void
    {
        $criteria = $query->criteria;

        foreach (self::ID_FILTERS as $property => $column) {
            if ($criteria->{$property} !== []) {
                $builder->whereIn("job_jobs.{$column}", $criteria->{$property});
            }
        }

        if ($criteria->workModes !== []) {
            $builder->whereIn('job_jobs.work_mode', array_map(static fn (WorkMode $mode): string => $mode->value, $criteria->workModes));
        }

        if ($criteria->skillIds !== []) {
            $builder->whereExists(fn (Builder $skills) => $skills
                ->selectRaw('1')
                ->from('job_job_skills')
                ->whereColumn('job_job_skills.job_id', 'job_jobs.id')
                ->whereIn('job_job_skills.skill_id', $criteria->skillIds));
        }

        if ($criteria->salaryMin !== null) {
            // Same period, a salary the company shows, and an upper bound (the maximum, or the minimum when that is all there is) that reaches the amount.
            // The column names are wrapped by the grammar, so the table prefix is applied (a raw `job_jobs.salary_max` would miss it).
            $grammar = $builder->getGrammar();

            $builder->where('job_jobs.salary_visible', true)
                ->where('job_jobs.salary_period_id', $criteria->salaryPeriodId)
                ->whereRaw('COALESCE('.$grammar->wrap('job_jobs.salary_max').', '.$grammar->wrap('job_jobs.salary_min').') >= ?', [$criteria->salaryMin]);
        }

        foreach ($query->words as $word) {
            $this->matchWord($builder, $word);
        }
    }

    /** A word matches through the title, one of the tags it matches, or one of the companies whose name it matches. */
    private function matchWord(Builder $builder, SearchWord $word): void
    {
        $like = '%'.addcslashes($word->text, '%_\\').'%';

        $builder->where(function (Builder $match) use ($word, $like): void {
            $match->where('job_jobs.title', 'like', $like);

            if ($word->tagIds !== []) {
                $match->orWhereExists(fn (Builder $tags) => $tags
                    ->selectRaw('1')
                    ->from('job_job_tags')
                    ->whereColumn('job_job_tags.job_id', 'job_jobs.id')
                    ->whereIn('job_job_tags.tag_id', $word->tagIds));
            }

            if ($word->companyIds !== []) {
                $match->orWhereIn('job_jobs.company_id', $word->companyIds);
            }
        });
    }

    private function toHit(stdClass $row): JobSearchHit
    {
        return new JobSearchHit(
            (int) $row->id,
            (string) $row->slug,
            (string) $row->title,
            (int) $row->company_id,
            $this->nullableInt($row->province_id),
            $this->nullableInt($row->city_id),
            $this->nullableInt($row->job_type_id),
            $this->nullableInt($row->job_level_id),
            $row->work_mode === null ? null : WorkMode::from((string) $row->work_mode),
            $this->nullableInt($row->salary_min),
            $this->nullableInt($row->salary_max),
            $this->nullableInt($row->salary_period_id),
            (bool) $row->salary_visible,
            $row->deadline === null ? null : new DateTimeImmutable((string) $row->deadline, new DateTimeZone('UTC')),
            $row->published_at === null ? null : new DateTimeImmutable((string) $row->published_at, new DateTimeZone('UTC')),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
