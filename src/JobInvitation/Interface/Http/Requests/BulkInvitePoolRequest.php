<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\JobInvitation\Application\BulkInviteFromPool;
use Src\JobInvitation\Domain\Invitation;

/** The body that invites talent pool entries to a job: `entry_ids` (1 to 50 distinct entries of the company's pool) and an optional `note` (plain text; its length is checked by the domain). */
final class BulkInvitePoolRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entry_ids' => ['required', 'array', 'min:1', 'max:'.BulkInviteFromPool::MAX_ENTRIES],
            'entry_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'note' => ['nullable', 'string', 'max:'.(Invitation::MAX_NOTE_LENGTH * 4)],
        ];
    }

    /** @return list<int> */
    public function entryIds(): array
    {
        return array_map('intval', array_values((array) $this->input('entry_ids', [])));
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
