<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Src\Recruitment\Application\ApplicantFilters;
use Src\Recruitment\Application\RecruiterApplicationQuery;

/**
 * The search, filters and sort the lists of applicants share (`docs/design/phase-8-applicant-search.md` sections 2 and 3). A malformed value or an unknown filter is
 * refused with 422 by the validation of the request that uses this trait.
 */
trait ApplicantListFilters
{
    /** The filter keys of the profile, score, origin and process filters, to add to the `array:` rule of the request. */
    private const string APPLICANT_FILTER_KEYS = 'province_id,city_id,education_min,experience_min,experience_max,skill_ids,salary_max,match_min,rating_min,applied_from,applied_to,invited,returning,state';

    private const string ID_LIST = '/^[1-9][0-9]*(,[1-9][0-9]*)*$/';

    private const string STATE_LIST = '/^(interview_scheduled|offer_pending|test_pending)(,(interview_scheduled|offer_pending|test_pending))*$/';

    /** Sorts that run both ways with a leading `-` for newest, highest or longest first. */
    private const array SORTS = ['applied_at', 'days_in_stage', 'match_score', 'rating'];

    /** @return array<string, mixed> */
    protected function applicantFilterRules(): array
    {
        return [
            'filter.q' => ['nullable', 'string', 'min:2', 'max:100'],
            'filter.province_id' => ['nullable', 'integer', 'min:1'],
            'filter.city_id' => ['nullable', 'integer', 'min:1'],
            'filter.education_min' => ['nullable', 'integer', 'min:1'],
            // Not above the maximum, when a maximum is given (`lte` alone fails when the other field is missing).
            'filter.experience_min' => ['nullable', 'integer', 'min:0', 'max:60', ...($this->filled('filter.experience_max') ? ['lte:filter.experience_max'] : [])],
            'filter.experience_max' => ['nullable', 'integer', 'min:0', 'max:60'],
            'filter.skill_ids' => ['nullable', 'string', 'max:200', 'regex:'.self::ID_LIST],
            'filter.salary_max' => ['nullable', 'integer', 'min:0'],
            'filter.match_min' => ['nullable', 'integer', 'between:0,100'],
            'filter.rating_min' => ['nullable', 'integer', 'between:1,5'],
            'filter.applied_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.applied_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.applied_from'],
            'filter.invited' => ['nullable', 'in:true,false,1,0'],
            'filter.returning' => ['nullable', 'in:true,false,1,0'],
            'filter.state' => ['nullable', 'string', 'regex:'.self::STATE_LIST],
            'sort' => ['nullable', 'in:'.implode(',', [...self::SORTS, ...array_map(static fn (string $sort): string => '-'.$sort, self::SORTS)])],
        ];
    }

    /** The search text, or null when there is none. */
    protected function searchText(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    protected function applicantFilters(): ApplicantFilters
    {
        return new ApplicantFilters(
            $this->filled('filter.province_id') ? $this->integer('filter.province_id') : null,
            $this->filled('filter.city_id') ? $this->integer('filter.city_id') : null,
            $this->filled('filter.education_min') ? $this->integer('filter.education_min') : null,
            $this->filled('filter.experience_min') ? $this->integer('filter.experience_min') : null,
            $this->filled('filter.experience_max') ? $this->integer('filter.experience_max') : null,
            $this->filled('filter.skill_ids') ? $this->idList('filter.skill_ids') : [],
            $this->filled('filter.salary_max') ? $this->integer('filter.salary_max') : null,
            $this->filled('filter.match_min') ? $this->integer('filter.match_min') : null,
            $this->filled('filter.rating_min') ? $this->integer('filter.rating_min') : null,
            $this->filled('filter.applied_from') ? $this->string('filter.applied_from')->toString() : null,
            $this->filled('filter.applied_to') ? $this->string('filter.applied_to')->toString() : null,
            $this->filled('filter.invited') ? $this->boolean('filter.invited') : null,
            $this->filled('filter.returning') ? $this->boolean('filter.returning') : null,
            $this->filled('filter.state') ? array_values(array_unique(explode(',', $this->string('filter.state')->toString()))) : [],
        );
    }

    /** @return list<int> */
    protected function idList(string $key): array
    {
        return array_values(array_unique(array_map('intval', explode(',', $this->string($key)->toString()))));
    }

    /** The sort key without its direction (`applied_at` when left out). */
    protected function sortKey(): string
    {
        return ltrim((string) $this->input('sort', '-'.RecruiterApplicationQuery::SORT_APPLIED_AT), '-');
    }

    /** Whether the list runs in descending order of the sort key: newest, highest or longest first. A sort without `-` runs the other way; the default is newest first. */
    protected function descending(): bool
    {
        return str_starts_with((string) $this->input('sort', '-'.RecruiterApplicationQuery::SORT_APPLIED_AT), '-');
    }
}
