<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\JobInvitation\Domain\Invitation;

/** The body that invites a candidate: `candidate` (the slug from the candidate search) and an optional `note` (plain text). The length of the note is checked by the domain. */
final class CreateInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:'.(Invitation::MAX_NOTE_LENGTH * 4)],
        ];
    }

    public function candidate(): string
    {
        return $this->string('candidate')->toString();
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
