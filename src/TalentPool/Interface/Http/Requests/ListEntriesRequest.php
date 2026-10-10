<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[category_id]`, `page` and `per_page`. Any other filter is refused with 422. */
final class ListEntriesRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:category_id'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function categoryId(): ?int
    {
        return $this->filled('filter.category_id') ? $this->integer('filter.category_id') : null;
    }
}
