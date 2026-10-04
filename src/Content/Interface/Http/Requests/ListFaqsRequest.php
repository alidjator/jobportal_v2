<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Allowed filter: `filter[category_id]`. Any other filter key is refused with 422. */
final class ListFaqsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:category_id'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function categoryId(): ?int
    {
        return $this->filled('filter.category_id') ? $this->integer('filter.category_id') : null;
    }
}
