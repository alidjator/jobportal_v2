<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Validation\Rule;
use Src\Company\Domain\RejectionReason;

/** Reject a document: the submission seen, one reason code, and a note of at most 500 characters that is required when the reason is `other`. */
final class RejectCompanyDocumentRequest extends ApproveCompanyDocumentRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => ['required', 'string', Rule::enum(RejectionReason::class)],
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,'.RejectionReason::Other->value],
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
