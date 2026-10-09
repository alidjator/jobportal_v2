<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\JobInvitation\Domain\Invitation;

/** The body that declines an invitation: an optional `reason` (plain text). The length of the reason is checked by the domain. */
final class DeclineInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:'.(Invitation::MAX_REASON_LENGTH * 4)]];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }
}
