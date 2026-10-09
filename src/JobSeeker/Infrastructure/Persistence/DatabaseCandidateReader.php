<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\JobSeeker\Application\Candidates\CandidateReader;
use Src\JobSeeker\Application\Candidates\CandidateRow;
use Src\JobSeeker\Application\Candidates\CandidateSearchQuery;
use Src\JobSeeker\Domain\Availability;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

/**
 * The candidate search over the profile tables. It reads the account columns it needs (name, status, picture) straight from `identity_users`: asking Identity
 * for every candidate of a page would take a query per row, and the filter on the account status has to run inside the same query.
 */
final readonly class DatabaseCandidateReader implements CandidateReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function search(CandidateSearchQuery $query, PageRequest $request, DateTimeImmutable $today): Page
    {
        $total = $this->filtered($query, $today)->count();
        $rows = $total === 0 ? [] : $this->filtered($query, $today)
            ->orderByDesc('p.updated_at')->orderByDesc('p.id')
            ->offset($request->offset())->limit($request->perPage)
            ->get($this->columns())->all();

        return new Page($this->candidates($rows), $total, $request);
    }

    public function findBySlug(string $slug): ?CandidateRow
    {
        $row = $this->searchable()->where('p.slug', $slug)->first($this->columns());

        return $row === null ? null : $this->candidates([$row])[0];
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'p.user_id', 'p.slug', 'u.name', 'u.avatar_path', 'p.headline', 'p.province_id', 'p.city_id', 'p.experience_level_id', 'p.availability', 'p.updated_at',
        ];
    }

    /** The candidates anyone may find, before any filter. */
    private function searchable(): Builder
    {
        return $this->connection->table('jobseeker_profiles as p')
            ->join('identity_users as u', 'u.id', '=', 'p.user_id')
            ->where('p.is_discoverable', true)
            ->whereNotNull('p.slug')
            ->where('u.type', 'job_seeker')
            ->where('u.status', 'active')
            ->whereNull('u.anonymized_at')
            ->whereNotNull('p.phone')
            ->whereNotNull('p.province_id')
            ->whereNotNull('p.city_id')
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('jobseeker_resumes as r')->whereColumn('r.user_id', 'p.user_id'))
            ->where(fn (Builder $q) => $q
                ->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_experiences as x')->whereColumn('x.user_id', 'p.user_id'))
                ->orWhereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_educations as d')->whereColumn('d.user_id', 'p.user_id')));
    }

    private function filtered(CandidateSearchQuery $query, DateTimeImmutable $today): Builder
    {
        $builder = $this->searchable();

        if ($query->keyword !== null) {
            $like = '%'.addcslashes($query->keyword, '\\%_').'%';
            $builder->where(fn (Builder $q) => $q
                ->where('p.headline', 'like', $like)
                ->orWhere('p.summary', 'like', $like)
                ->orWhereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_experiences as x')->whereColumn('x.user_id', 'p.user_id')->where('x.job_title', 'like', $like)));
        }

        foreach ($query->skillIds as $skillId) {
            $builder->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_skills as s')->whereColumn('s.user_id', 'p.user_id')->where('s.skill_id', $skillId));
        }

        foreach ($query->languageIds as $languageId) {
            $builder->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_languages as l')->whereColumn('l.user_id', 'p.user_id')->where('l.language_id', $languageId));
        }

        if ($query->provinceId !== null) {
            $builder->where('p.province_id', $query->provinceId);
        }

        if ($query->cityId !== null) {
            $builder->where('p.city_id', $query->cityId);
        }

        if ($query->experienceLevelIds !== []) {
            $builder->whereIn('p.experience_level_id', $query->experienceLevelIds);
        }

        if ($query->minEducationLevelId !== null) {
            $minimum = $this->connection->table('reference_education_levels')->where('id', $query->minEducationLevelId)->select('rank');
            $builder->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('jobseeker_educations as d')
                ->join('reference_education_levels as el', 'el.id', '=', 'd.education_level_id')
                ->whereColumn('d.user_id', 'p.user_id')
                ->where('el.rank', '>=', $minimum));
        }

        if ($query->availabilities !== []) {
            $builder->whereIn('p.availability', array_map(static fn (Availability $a): string => $a->value, $query->availabilities));
        }

        if ($query->maxExpectedSalary !== null) {
            // A candidate who gave no expectation does not match, as a job with a hidden salary does not match a minimum salary.
            $builder->whereNotNull('p.expected_salary')->where('p.expected_salary', '<=', $query->maxExpectedSalary)->where('p.expected_salary_period_id', $query->salaryPeriodId);
        }

        if ($query->religionIds !== []) {
            $builder->whereIn('p.religion_id', $query->religionIds);
        }

        if ($query->nationalityIds !== []) {
            $builder->whereIn('p.nationality_id', $query->nationalityIds);
        }

        if ($query->gender !== null) {
            $builder->where('p.gender', $query->gender->value);
        }

        if ($query->minAge !== null) {
            // At least N years old: born on or before the same day N years ago.
            $builder->whereNotNull('p.birth_date')->where('p.birth_date', '<=', $today->modify("-{$query->minAge} years")->format('Y-m-d'));
        }

        if ($query->maxAge !== null) {
            // At most N years old: born after the same day N + 1 years ago.
            $builder->whereNotNull('p.birth_date')->where('p.birth_date', '>', $today->modify('-'.($query->maxAge + 1).' years')->format('Y-m-d'));
        }

        return $builder;
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<CandidateRow>
     */
    private function candidates(array $rows): array
    {
        $userIds = array_map(static fn (stdClass $row): int => (int) $row->user_id, $rows);
        $highest = $this->highestEducationLevels($userIds);
        $utc = new DateTimeZone('UTC');
        $id = static fn (mixed $value): ?int => $value === null ? null : (int) $value;

        return array_map(fn (stdClass $row): CandidateRow => new CandidateRow(
            (int) $row->user_id,
            (string) $row->slug,
            (string) $row->name,
            $row->avatar_path,
            $row->headline,
            $id($row->province_id),
            $id($row->city_id),
            $id($row->experience_level_id),
            $row->availability === null ? null : Availability::from((string) $row->availability),
            $highest[(int) $row->user_id] ?? null,
            new DateTimeImmutable((string) $row->updated_at, $utc),
        ), $rows);
    }

    /**
     * The education level of the highest rank each user has, in one query.
     *
     * @param  list<int>  $userIds
     * @return array<int, int> user id => education level id
     */
    private function highestEducationLevels(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->connection->table('jobseeker_educations as d')
            ->join('reference_education_levels as el', 'el.id', '=', 'd.education_level_id')
            ->whereIn('d.user_id', $userIds)
            ->orderBy('el.rank')
            ->get(['d.user_id', 'd.education_level_id']);

        $highest = [];

        // Ascending by rank, so the last row of a user holds their highest level.
        foreach ($rows as $row) {
            $highest[(int) $row->user_id] = (int) $row->education_level_id;
        }

        return $highest;
    }
}
