<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\RejectionReason;

/** The stages whose remaining applications are rejected, and the one reason (and optional note) for all of them. Whether the note is needed and its length are use case rules. */
final class RejectRemainingRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'reason' => ['required', 'string', Rule::enum(RejectionReason::class)],
            'note' => ['nullable', 'string'],
        ];
    }

    /** @return list<int> */
    public function stageIds(): array
    {
        return array_map('intval', array_values((array) $this->validated('stage_ids')));
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
