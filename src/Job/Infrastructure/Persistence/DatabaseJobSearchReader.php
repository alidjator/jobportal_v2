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
use Src\Job\Application\Browse\SimilarJobsQuery;
use Src\Job\Domain\JobStatus;
use Src\Job\Domain\JobWorkPlace;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\NullOrder;
use stdClass;

/**
 * Only live jobs are searched: `active`, not on a report hold, deadline not before today (Jakarta time), company not in the excluded list. Table and column names are fixed in this
 * class (never taken from input); every value is a bound parameter. The keyword is matched against the full text vector of the job (`JobSearchVectors`), ignoring case and accents.
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

    /** The columns of a hit. */
    private const array COLUMNS = [
        'job_jobs.id', 'job_jobs.slug', 'job_jobs.title', 'job_jobs.company_id', 'job_jobs.province_id', 'job_jobs.city_id', 'job_jobs.job_type_id',
        'job_jobs.job_level_id', 'job_jobs.work_mode', 'job_jobs.salary_min', 'job_jobs.salary_max', 'job_jobs.salary_period_id', 'job_jobs.salary_visible',
        'job_jobs.deadline', 'job_jobs.published_at', 'job_jobs.summary', 'job_jobs.salary_note',
    ];

    /**
     * The keyword as a full text query: PostgreSQL splits and unaccents it the way it indexed the job, every word must match and the last one also as a prefix
     * (`'dev' & 'backend'` becomes `'dev' & 'backend':*`). The job alerts match a new job with the same expression (`DatabaseJobAlertRepository`).
     */
    private const string TS_QUERY = <<<'SQL'
        to_tsquery('simple', regexp_replace(plainto_tsquery('simple', immutable_unaccent(?))::text, '''$', ''':*'))
        SQL;

    public function __construct(private ConnectionInterface $connection) {}

    public function page(JobSearchQuery $query, PageRequest $request): Page
    {
        $filtered = $this->live($query);

        $total = (clone $filtered)->count();

        $rows = $this->ordered($filtered->select(self::COLUMNS), $query)
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->all();

        return new Page($this->toHits($rows), $total, $request);
    }

    public function topHits(JobSearchQuery $query, int $limit): array
    {
        $rows = $this->ordered($this->live($query)->select(self::COLUMNS), $query, byRelevance: true)->limit($limit)->get()->all();

        return $this->toHits($rows);
    }

    public function similarTo(JobSearchQuery $query, SimilarJobsQuery $subject, int $limit, int $perCompany): array
    {
        $builder = $this->live($query)->where('job_jobs.id', '<>', $subject->jobId);

        $builder->where(function (Builder $same) use ($subject): void {
            if ($subject->roleId !== null) {
                $same->where('job_jobs.role_id', $subject->roleId);
            }

            if ($subject->categoryId !== null) {
                $same->orWhere('job_jobs.category_id', $subject->categoryId);
            }

            // A subject without role and category is similar to nothing.
            if ($subject->roleId === null && $subject->categoryId === null) {
                $same->whereRaw('1 = 0');
            }
        });

        $grammar = $builder->getGrammar();
        $score = [];
        $bindings = [];

        foreach ([['job_jobs.role_id', $subject->roleId], ['job_jobs.city_id', $subject->cityId], ['job_jobs.province_id', $subject->provinceId]] as [$column, $value]) {
            if ($value !== null) {
                $score[] = 'CASE WHEN '.$grammar->wrap($column).' = ? THEN 1 ELSE 0 END';
                $bindings[] = $value;
            }
        }

        $order = [];

        foreach ($score as $index => $expression) {
            $order[] = [$expression, [$bindings[$index]]];
        }

        if ($subject->levelIds !== []) {
            $order[] = ['CASE WHEN '.$grammar->wrap('job_jobs.job_level_id').' IN ('.implode(', ', array_fill(0, count($subject->levelIds), '?')).') THEN 1 ELSE 0 END', $subject->levelIds];
        }

        foreach ($order as [$expression, $expressionBindings]) {
            $builder->orderByRaw($expression.' DESC', $expressionBindings);
        }

        $rows = $builder->select(self::COLUMNS)->orderByRaw(NullOrder::sql('job_jobs.published_at', 'desc'))->orderBy('job_jobs.id', 'desc')->limit($limit * 10)->get()->all();

        $perCompanyCount = [];
        $chosen = [];

        foreach ($rows as $row) {
            $company = (int) $row->company_id;

            if (($perCompanyCount[$company] ?? 0) >= $perCompany) {
                continue;
            }

            $perCompanyCount[$company] = ($perCompanyCount[$company] ?? 0) + 1;
            $chosen[] = $row;

            if (count($chosen) === $limit) {
                break;
            }
        }

        return $this->toHits($chosen);
    }

    public function hitsOf(JobSearchQuery $query, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        $rows = $this->live($query)->whereIn('job_jobs.id', $jobIds)->select(self::COLUMNS)->get()->all();

        return $this->toHits($rows);
    }

    /**
     * The order of the query: by relevance when asked and a keyword is given, else the chosen date order (newest first for `match`, whose ranking is done by the
     * caller). `$byRelevance` makes the keyword order win over the sort, for the pool that is scored.
     */
    private function ordered(Builder $builder, JobSearchQuery $query, bool $byRelevance = false): Builder
    {
        if ($query->keyword !== null && ($byRelevance || $query->criteria->sort === JobSearchSort::Relevance)) {
            $vector = $builder->getGrammar()->wrap('job_jobs.search_vector');

            return $builder
                ->orderByRaw("ts_rank_cd({$vector}, ".self::TS_QUERY.') desc', [$query->keyword])
                ->orderByRaw(NullOrder::sql('job_jobs.published_at', 'desc'))
                ->orderBy('job_jobs.id', 'desc');
        }

        [$column, $direction] = match ($query->criteria->sort) {
            JobSearchSort::PublishedAtAscending => ['job_jobs.published_at', 'asc'],
            JobSearchSort::DeadlineAscending => ['job_jobs.deadline', 'asc'],
            JobSearchSort::DeadlineDescending => ['job_jobs.deadline', 'desc'],
            default => ['job_jobs.published_at', 'desc'],
        };

        return $builder->orderByRaw(NullOrder::sql($column, $direction))->orderBy('job_jobs.id', $direction);
    }

    /** The live jobs of companies the public may see that match the criteria and the window of the query. */
    private function live(JobSearchQuery $query): Builder
    {
        $filtered = $this->connection->table('job_jobs')
            ->where('job_jobs.status', JobStatus::Active->value)
            ->whereNull('job_jobs.report_hold_at')
            ->where('job_jobs.deadline', '>=', $query->today->format('Y-m-d'));

        if ($query->excludedCompanyIds !== []) {
            $filtered->whereNotIn('job_jobs.company_id', $query->excludedCompanyIds);
        }

        $this->applyFilters($filtered, $query);
        $this->applyWindow($filtered, $query);

        return $filtered;
    }

    /** What a job alert adds: a window on the first go-live (after the first time, up to and including the second) and one job. */
    private function applyWindow(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->publishedAfter !== null) {
            $builder->where('job_jobs.published_at', '>', $query->publishedAfter->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        }

        if ($query->publishedUntil !== null) {
            $builder->where('job_jobs.published_at', '<=', $query->publishedUntil->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        }

        if ($query->postedSince !== null) {
            $builder->where('job_jobs.published_at', '>=', $query->postedSince->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        }

        if ($query->onlyJobId !== null) {
            $builder->where('job_jobs.id', $query->onlyJobId);
        }
    }

    private function applyFilters(Builder $builder, JobSearchQuery $query): void
    {
        $criteria = $query->criteria;

        foreach (self::ID_FILTERS as $property => $column) {
            if ($criteria->{$property} === []) {
                continue;
            }

            if ($column === 'province_id' || $column === 'city_id') {
                // A job with several work locations matches a place filter through any of them.
                $builder->where(fn (Builder $place) => $place
                    ->whereIn("job_jobs.{$column}", $criteria->{$property})
                    ->orWhereExists(fn (Builder $places) => $places
                        ->selectRaw('1')
                        ->from('job_job_locations')
                        ->whereColumn('job_job_locations.job_id', 'job_jobs.id')
                        ->whereIn("job_job_locations.{$column}", $criteria->{$property})));

                continue;
            }

            $builder->whereIn("job_jobs.{$column}", $criteria->{$property});
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

        if ($query->keyword !== null) {
            $builder->whereRaw($builder->getGrammar()->wrap('job_jobs.search_vector').' @@ '.self::TS_QUERY, [$query->keyword]);
        }
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<JobSearchHit>
     */
    private function toHits(array $rows): array
    {
        $places = $this->placesOf(array_map(static fn (stdClass $row): int => (int) $row->id, $rows));

        return array_map(fn (stdClass $row): JobSearchHit => $this->toHit($row, $places[(int) $row->id] ?? []), $rows);
    }

    /**
     * The company locations of several jobs in one query, the main one of each job first.
     *
     * @param  list<int>  $jobIds
     * @return array<int, list<JobWorkPlace>>
     */
    private function placesOf(array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        $places = [];

        foreach ($this->connection->table('job_job_locations')->whereIn('job_id', $jobIds)->orderBy('job_id')->orderBy('position')->get() as $row) {
            $places[(int) $row->job_id][] = new JobWorkPlace(
                (int) $row->location_id,
                (string) $row->name,
                (string) $row->type,
                $this->nullableInt($row->province_id),
                $this->nullableInt($row->city_id),
                $this->nullableInt($row->subdistrict_id),
                $this->nullableInt($row->village_id),
                $this->nullableInt($row->postal_code_id),
                $row->address_line === null ? null : (string) $row->address_line,
            );
        }

        return $places;
    }

    /** @param  list<JobWorkPlace>  $places */
    private function toHit(stdClass $row, array $places): JobSearchHit
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
            $row->summary === null ? null : (string) $row->summary,
            $row->salary_note === null ? null : (string) $row->salary_note,
            $places,
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
