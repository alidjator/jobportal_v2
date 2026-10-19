<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\RecruiterApplicationQuery;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[stage_id]`, `filter[stage_type]`, `filter[meets_must_have]` (true or false), `filter[q]` (at least 2 characters of the name, headline, job titles,
 * employers, skills, schools or city), `filter[stale]` (true: only the stale ones), `filter[interview_state]` (`not_scheduled`, `pending`, `confirmed` or `completed`:
 * applications in an interview stage in that state), the profile, score, origin and process filters of `ApplicantListFilters`, `sort` (`applied_at`, `days_in_stage`,
 * `match_score` or `rating`, each with a leading `-` for the other direction; newest first when left out, `-days_in_stage` is the longest wait first and unscored or
 * unrated applications come last), `page` and `per_page`. Any other filter is refused with 422.
 */
final class ListJobApplicationsRequest extends FormRequest
{
    use ApplicantListFilters;
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            ...$this->applicantFilterRules(),
            'filter' => ['nullable', 'array:stage_id,stage_type,meets_must_have,q,stale,interview_state,'.self::APPLICANT_FILTER_KEYS],
            'filter.stage_id' => ['nullable', 'integer', 'min:1'],
            'filter.stage_type' => ['nullable', 'string', Rule::enum(StageType::class)],
            'filter.meets_must_have' => ['nullable', 'in:true,false,1,0'],
            'filter.stale' => ['nullable', 'in:true,false,1,0'],
            'filter.interview_state' => ['nullable', 'in:not_scheduled,pending,confirmed,completed'],
        ];
    }

    /** The stale limit is the company's: the use case fills it in. */
    public function toQuery(int $jobId): RecruiterApplicationQuery
    {
        return new RecruiterApplicationQuery(
            $jobId,
            $this->filled('filter.stage_id') ? $this->integer('filter.stage_id') : null,
            $this->filled('filter.stage_type') ? StageType::from($this->string('filter.stage_type')->toString()) : null,
            $this->filled('filter.meets_must_have') ? $this->boolean('filter.meets_must_have') : null,
            $this->searchText(),
            $this->descending(),
            $this->boolean('filter.stale'),
            7,
            $this->sortKey(),
            [],
            $this->filled('filter.interview_state') ? $this->string('filter.interview_state')->toString() : null,
            $this->applicantFilters(),
        );
    }
}
