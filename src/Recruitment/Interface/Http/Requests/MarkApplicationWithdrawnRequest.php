<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The reason and the note of a withdrawal marked by the team. The reason list (and `other` needing a note) and the note length are use case rules. */
final class MarkApplicationWithdrawnRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string'], 'note' => ['nullable', 'string']];
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) ? $reason : null;
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }
}
