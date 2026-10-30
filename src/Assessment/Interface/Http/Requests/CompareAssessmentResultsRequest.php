<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Application\Comparison\AssessmentComparisonQuery;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[stage_type]` (opens the final stages; the active applications are listed otherwise), `filter[stage_id]` (only the tests sent in that assessment stage),
 * `filter[test_id]` (the test whose sections are the columns; the most used one when left out), `sort` (`percentage`, `submitted_at` or `match_score`, highest first;
 * `percentage` when left out, `submitted_at` for a battery), `page` and `per_page`. Any other filter is refused with 422.
 */
final class CompareAssessmentResultsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:stage_id,stage_type,test_id'],
            'filter.stage_id' => ['nullable', 'integer', 'min:1'],
            'filter.stage_type' => ['nullable', 'string', 'in:applied,screening,assessment,interview,offer,hired,rejected,withdrawn'],
            'filter.test_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:'.implode(',', [AssessmentComparisonQuery::SORT_PERCENTAGE, AssessmentComparisonQuery::SORT_SUBMITTED_AT, AssessmentComparisonQuery::SORT_MATCH_SCORE])],
        ];
    }

    public function toQuery(int $jobId): AssessmentComparisonQuery
    {
        return new AssessmentComparisonQuery(
            $jobId,
            $this->filled('filter.stage_type') ? $this->string('filter.stage_type')->toString() : null,
            $this->filled('filter.stage_id') ? $this->integer('filter.stage_id') : null,
            $this->filled('filter.test_id') ? $this->integer('filter.test_id') : null,
            $this->string('sort', AssessmentComparisonQuery::SORT_PERCENTAGE)->toString(),
        );
    }
}
