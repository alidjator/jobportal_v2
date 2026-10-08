<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\RejectionReason;

/** The selection, and the one reason (and optional note) for all of it. Whether the note is needed and its length are use case rules. */
final class BulkRejectApplicationsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...BulkSelection::rules(),
            'reason' => ['required', 'string', Rule::enum(RejectionReason::class)],
            'note' => ['nullable', 'string'],
        ];
    }

    /** @return list<int> */
    public function applicationIds(): array
    {
        return BulkSelection::ids($this->validated('application_ids'));
    }

    public function reason(): RejectionReason
    {
        return RejectionReason::from($this->string('reason')->toString());
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }
}
