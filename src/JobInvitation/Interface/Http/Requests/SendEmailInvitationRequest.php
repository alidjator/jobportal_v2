<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationChannel;
use Src\JobInvitation\Domain\Invitation;

/**
 * A multipart body that invites a person by email: `name`, `email`, `channel`, an optional `note` and an optional `cv` (a PDF). Only the shape of the CV is checked here; its size and
 * content are checked by the use case, which reads the content itself and ignores the type the client sent. The note's link rule and length are checked by the domain.
 */
final class SendEmailInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:'.EmailInvitation::NAME_MAX_LENGTH],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'channel' => ['required', 'string', Rule::enum(EmailInvitationChannel::class)],
            'note' => ['nullable', 'string', 'max:'.(Invitation::MAX_NOTE_LENGTH * 4)],
            'cv' => ['nullable', 'file'],
        ];
    }

    public function personName(): string
    {
        return $this->string('name')->trim()->toString();
    }

    public function email(): string
    {
        return $this->string('email')->trim()->toString();
    }

    public function channel(): EmailInvitationChannel
    {
        return EmailInvitationChannel::from($this->string('channel')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }

    public function cvContents(): ?string
    {
        return $this->hasFile('cv') ? (string) $this->file('cv')?->getContent() : null;
    }

    public function cvName(): ?string
    {
        return $this->hasFile('cv') ? (string) $this->file('cv')?->getClientOriginalName() : null;
    }
}
