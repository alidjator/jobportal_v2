<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Review\Domain\ReviewQuery;
use Src\Review\Domain\ReviewStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`pending`, `published`, `rejected`, `removed`), `filter[company_id]`, `filter[reported]` (only reviews with an open report), `page`, `per_page`. */
final class ListAdminReviewsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status,company_id,reported'],
            'filter.status' => ['nullable', 'string', Rule::enum(ReviewStatus::class)],
            'filter.company_id' => ['nullable', 'integer', 'min:1'],
            'filter.reported' => ['nullable', 'boolean'],
        ];
    }

    public function reviewQuery(): ReviewQuery
    {
        return new ReviewQuery(
            $this->filled('filter.status') ? ReviewStatus::from($this->string('filter.status')->toString()) : null,
            $this->filled('filter.company_id') ? $this->integer('filter.company_id') : null,
            $this->boolean('filter.reported'),
        );
    }
}
