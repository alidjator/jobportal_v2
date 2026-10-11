<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`invited`, `started`, `submitted`, `expired` or `cancelled`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListMyInvitationsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(InvitationStatus::class)],
        ];
    }

    public function status(): ?InvitationStatus
    {
        return $this->filled('filter.status') ? InvitationStatus::from($this->string('filter.status')->toString()) : null;
    }
}
