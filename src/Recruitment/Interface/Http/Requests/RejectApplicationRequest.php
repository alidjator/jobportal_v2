<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\RejectionReason;

/** The mandatory reason from the fixed list and an optional note (required for `other`: a use case rule). */
final class RejectApplicationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(RejectionReason::class)],
            'note' => ['nullable', 'string'],
        ];
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
