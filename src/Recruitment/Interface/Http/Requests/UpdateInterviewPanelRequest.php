<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\InterviewPanel;

/** The extra members of an interview's panel, as the whole list; an empty list leaves only the main interviewer. */
final class UpdateInterviewPanelRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'participant_ids' => ['present', 'array', 'max:'.InterviewPanel::EXTRA_MAX],
            'participant_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function participantIds(): array
    {
        return array_values(array_map('intval', $this->array('participant_ids')));
    }
}
