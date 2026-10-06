<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[q]` (a piece of the text), `filter[archived]` (true or false; the active questions when left out), `page` and `per_page`. Any other filter is refused with 422. */
final class ListScreeningQuestionsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,archived'],
            'filter.q' => ['nullable', 'string', 'max:100'],
            'filter.archived' => ['nullable', 'in:true,false,1,0'],
        ];
    }

    public function archived(): bool
    {
        return $this->filled('filter.archived') && $this->boolean('filter.archived');
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }
}
