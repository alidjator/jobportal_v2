<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\InterviewInput;

/**
 * The stage to move an application to, and the interview to schedule with the move (needed for an interview stage unless the application already has an
 * active one). Whether it is an active stage of the company is a use case rule.
 */
final class MoveApplicationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', 'min:1'],
            'interview' => ['nullable', 'array'],
            ...InterviewFields::rules('interview.', 'required_with:interview'),
        ];
    }

    public function interview(): ?InterviewInput
    {
        $data = $this->validated('interview');

        return is_array($data) && $data !== [] ? InterviewFields::input($data) : null;
    }

    public function stageId(): int
    {
        return $this->integer('stage_id');
    }
}
