<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[q]` (a piece of the name), `filter[job_level_id]`, `page` and `per_page`. Any other filter is refused with 422. */
final class ListScreeningPresetsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,job_level_id'],
            'filter.q' => ['nullable', 'string', 'max:100'],
            'filter.job_level_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function jobLevelId(): ?int
    {
        return $this->filled('filter.job_level_id') ? $this->integer('filter.job_level_id') : null;
    }
}
