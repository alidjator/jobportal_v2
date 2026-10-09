<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobInvitation\Domain\InvitationStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`sent`, `viewed`, `applied` or `declined`, the stored status), `page` and `per_page`. Any other filter is refused with 422. */
final class ListInvitationsRequest extends FormRequest
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
