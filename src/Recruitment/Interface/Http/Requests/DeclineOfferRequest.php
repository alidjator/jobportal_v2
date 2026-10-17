<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The optional reason and note of the job seeker who declines an offer. The reason list and the note length are use case rules. */
final class DeclineOfferRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string'], 'note' => ['nullable', 'string']];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) ? $reason : null;
    }
}
