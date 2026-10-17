<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\RecruiterApplicationQuery;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[stage_id]`, `filter[stage_type]`, `filter[meets_must_have]` (true or false), `filter[q]` (a piece of the name), `filter[stale]` (true: only the stale ones), `filter[interview_state]` (`not_scheduled`, `pending`, `confirmed` or `completed`: applications in an interview stage in that state), `sort`
 * (`applied_at` or `-applied_at`, `days_in_stage` or `-days_in_stage`; newest first when left out, `-days_in_stage` is the longest wait first), `page` and `per_page`. Any other filter is refused with 422.
 */
final class ListJobApplicationsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:stage_id,stage_type,meets_must_have,q,stale,interview_state'],
            'filter.stage_id' => ['nullable', 'integer', 'min:1'],
            'filter.stage_type' => ['nullable', 'string', Rule::enum(StageType::class)],
            'filter.meets_must_have' => ['nullable', 'in:true,false,1,0'],
            'filter.q' => ['nullable', 'string', 'max:100'],
            'filter.stale' => ['nullable', 'in:true,false,1,0'],
            'filter.interview_state' => ['nullable', 'in:not_scheduled,pending,confirmed,completed'],
            'sort' => ['nullable', 'in:applied_at,-applied_at,days_in_stage,-days_in_stage'],
        ];
    }

    /** The stale limit is the company's: the use case fills it in. */
    public function toQuery(int $jobId): RecruiterApplicationQuery
    {
        $sort = (string) $this->input('sort', '-applied_at');

        return new RecruiterApplicationQuery(
            $jobId,
            $this->filled('filter.stage_id') ? $this->integer('filter.stage_id') : null,
            $this->filled('filter.stage_type') ? StageType::from($this->string('filter.stage_type')->toString()) : null,
            $this->filled('filter.meets_must_have') ? $this->boolean('filter.meets_must_have') : null,
            $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null,
            ! in_array($sort, ['applied_at', 'days_in_stage'], true),
            $this->boolean('filter.stale'),
            7,
            ltrim($sort, '-'),
            false,
            $this->filled('filter.interview_state') ? $this->string('filter.interview_state')->toString() : null,
        );
    }
}
