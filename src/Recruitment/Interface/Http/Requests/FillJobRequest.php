<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Whether to close the job, the applications to reject with the reason `position_filled` (may be empty), and an optional team-only note. The note's length is a use case rule. */
final class FillJobRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'close' => ['required', 'boolean'],
            'reject_application_ids' => ['present', 'array'],
            'reject_application_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function close(): bool
    {
        return $this->boolean('close');
    }

    /** @return list<int> */
    public function applicationIds(): array
    {
        return BulkSelection::ids($this->validated('reject_application_ids'));
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }
}
