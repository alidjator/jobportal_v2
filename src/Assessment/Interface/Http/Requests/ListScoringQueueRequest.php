<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** `filter[my_scoring]=pending` is required (the only list this endpoint serves); any other filter is refused with 422. Paginated (`page`, `per_page`). */
final class ListScoringQueueRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['required', 'array:my_scoring'],
            'filter.my_scoring' => ['required', 'string', 'in:pending'],
        ];
    }
}
