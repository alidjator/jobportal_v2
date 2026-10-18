<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\JobInvitation\Domain\Invitation;

/**
 * The body that invites a candidate: `candidate` (the slug from the candidate search) or `talent_pool_entry_id` (an entry of the company's talent pool), never both,
 * and an optional `note` (plain text). The length of the note is checked by the domain.
 */
final class CreateInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate' => ['required_without:talent_pool_entry_id', 'prohibits:talent_pool_entry_id', 'nullable', 'string', 'max:40'],
            'talent_pool_entry_id' => ['required_without:candidate', 'prohibits:candidate', 'nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:'.(Invitation::MAX_NOTE_LENGTH * 4)],
        ];
    }

    public function candidate(): ?string
    {
        return $this->filled('candidate') ? $this->string('candidate')->toString() : null;
    }

    public function entryId(): ?int
    {
        return $this->filled('talent_pool_entry_id') ? $this->integer('talent_pool_entry_id') : null;
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
