<?php

namespace Src\Training\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[q]` (part of the course name), `page` and `per_page`. Any other filter is refused with 422. */
final class ListCoursesRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }
}
