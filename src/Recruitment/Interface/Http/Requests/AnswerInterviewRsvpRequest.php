<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\Rsvp;

/** The answer of a panel member to an interview invitation: `accepted` or `declined`, with an optional note. */
final class AnswerInterviewRsvpRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', Rule::in([Rsvp::Accepted->value, Rsvp::Declined->value])],
            'note' => ['nullable', 'string', 'max:'.InterviewRsvps::NOTE_MAX],
        ];
    }

    public function answer(): Rsvp
    {
        return Rsvp::from($this->string('answer')->toString());
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }
}
