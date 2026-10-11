<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[q]` (title), `filter[status]` (`draft`, `published` or `archived`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListTestsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,status'],
            'filter.q' => ['nullable', 'string', 'max:100'],
            'filter.status' => ['nullable', 'string', Rule::enum(TestStatus::class)],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function status(): ?TestStatus
    {
        return $this->filled('filter.status') ? TestStatus::from($this->string('filter.status')->toString()) : null;
    }
}
