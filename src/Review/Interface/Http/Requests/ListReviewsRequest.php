<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Review\Domain\ReviewSort;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `sort` (`newest`, `highest`, `lowest`), `page` and `per_page` (up to 50). */
final class ListReviewsRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_PER_PAGE = 50;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'sort' => ['nullable', 'string', Rule::enum(ReviewSort::class)],
        ];
    }

    public function sort(): ReviewSort
    {
        return $this->filled('sort') ? ReviewSort::from($this->string('sort')->toString()) : ReviewSort::Newest;
    }
}
