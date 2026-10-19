<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\Interview;

/** The team's optional message to the job seeker whose proposed time is refused (plain text); it is sent, not stored. */
final class RefuseInterviewProposalRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['message' => ['nullable', 'string', 'max:'.Interview::NOTE_MAX]];
    }

    public function message(): ?string
    {
        $message = $this->validated('message');

        return is_string($message) ? $message : null;
    }
}
