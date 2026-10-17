<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\Interview;

/** The new note for the candidate; null or an empty text clears it. */
final class UpdateInterviewNoteRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['note' => ['present', 'nullable', 'string', 'max:'.Interview::NOTE_MAX]];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }
}
