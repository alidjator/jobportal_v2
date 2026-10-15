<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\NameChangeStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`pending`, `approved`, `rejected` or `cancelled`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListNameChangeRequestsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(NameChangeStatus::class)],
        ];
    }

    public function status(): ?NameChangeStatus
    {
        return $this->filled('filter.status') ? NameChangeStatus::from($this->string('filter.status')->toString()) : null;
    }
}
