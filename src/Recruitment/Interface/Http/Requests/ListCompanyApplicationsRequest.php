<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\RecruiterApplicationQuery;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * The applicants across jobs: the filters of `ListJobApplicationsRequest` plus `filter[job_id]` (comma-separated job ids). Without `filter[stage_id]` or
 * `filter[stage_type]` only active applications are listed. Any other filter is refused with 422.
 */
final class ListCompanyApplicationsRequest extends FormRequest
{
    use ApplicantListFilters;
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            ...$this->applicantFilterRules(),
            'filter' => ['nullable', 'array:job_id,stage_id,stage_type,meets_must_have,q,stale,interview_state,'.self::APPLICANT_FILTER_KEYS],
            'filter.job_id' => ['nullable', 'string', 'max:200', 'regex:'.self::ID_LIST],
            'filter.stage_id' => ['nullable', 'integer', 'min:1'],
            'filter.stage_type' => ['nullable', 'string', Rule::enum(StageType::class)],
            'filter.meets_must_have' => ['nullable', 'in:true,false,1,0'],
            'filter.stale' => ['nullable', 'in:true,false,1,0'],
            'filter.interview_state' => ['nullable', 'in:not_scheduled,pending,confirmed,completed'],
        ];
    }

    /** The scope (the company and the jobs the member sees) and the stale limit are the company's: the use case fills them in. */
    public function toQuery(): RecruiterApplicationQuery
    {
        return new RecruiterApplicationQuery(
            null,
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
            null,
            $this->filled('filter.job_id') ? $this->idList('filter.job_id') : null,
        );
    }
}
