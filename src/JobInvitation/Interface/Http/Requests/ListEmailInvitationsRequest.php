<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobInvitation\Domain\EmailInvitationStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (the stored status), `page` and `per_page`. Any other filter is refused with 422. */
final class ListEmailInvitationsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(EmailInvitationStatus::class)],
        ];
    }

    public function status(): ?EmailInvitationStatus
    {
        return $this->filled('filter.status') ? EmailInvitationStatus::from($this->string('filter.status')->toString()) : null;
    }
}
