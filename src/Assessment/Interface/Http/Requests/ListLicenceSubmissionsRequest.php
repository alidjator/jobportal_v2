<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\LicenceStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`pending`, `approved` or `rejected`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListLicenceSubmissionsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(LicenceStatus::class)],
        ];
    }

    public function status(): ?LicenceStatus
    {
        return $this->filled('filter.status') ? LicenceStatus::from($this->string('filter.status')->toString()) : null;
    }
}
