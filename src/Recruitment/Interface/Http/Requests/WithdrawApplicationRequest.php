<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The optional reason and note of a withdrawal. The reason list and the note length are use case rules. */
final class WithdrawApplicationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string'], 'note' => ['nullable', 'string']];
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) ? $reason : null;
    }
}
