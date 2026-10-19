<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\InterviewComparisonQuery;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[stage_id]` (an interview stage), `filter[stage_type]` (opens the final stages; the active applications are listed otherwise), `filter[kit_id]`
 * (the kit whose questions are the columns; the most used one when left out), `sort` (`score`, `yes` or `match_score`, highest first; `score` when left out), `page`
 * and `per_page`. Any other filter is refused with 422.
 */
final class CompareInterviewResultsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:stage_id,stage_type,kit_id'],
            'filter.stage_id' => ['nullable', 'integer', 'min:1'],
            'filter.stage_type' => ['nullable', 'string', Rule::enum(StageType::class)],
            'filter.kit_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:'.implode(',', [InterviewComparisonQuery::SORT_SCORE, InterviewComparisonQuery::SORT_YES, InterviewComparisonQuery::SORT_MATCH_SCORE])],
        ];
    }

    public function toQuery(int $jobId): InterviewComparisonQuery
    {
        return new InterviewComparisonQuery(
            $jobId,
            $this->filled('filter.stage_id') ? $this->integer('filter.stage_id') : null,
            $this->filled('filter.stage_type') ? StageType::from($this->string('filter.stage_type')->toString()) : null,
            $this->filled('filter.kit_id') ? $this->integer('filter.kit_id') : null,
            $this->string('sort', InterviewComparisonQuery::SORT_SCORE)->toString(),
        );
    }
}
