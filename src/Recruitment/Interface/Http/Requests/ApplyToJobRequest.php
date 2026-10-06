<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\ApplyToJobInput;

/** The resume, the optional cover letter and the answers of an application. Whether the answers fit the job's questions is a use case rule. */
final class ApplyToJobRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'resume_id' => ['required', 'integer', 'min:1'],
            'cover_letter' => ['nullable', 'string'],
            'answers' => ['nullable', 'array', 'max:50'],
            'answers.*.question_id' => ['required', 'integer', 'min:1', 'distinct'],
            'answers.*.value' => ['present'],
        ];
    }

    public function toInput(): ApplyToJobInput
    {
        $answers = [];

        foreach ((array) $this->input('answers', []) as $answer) {
            $answers[(int) $answer['question_id']] = $answer['value'];
        }

        $coverLetter = $this->input('cover_letter');

        return new ApplyToJobInput((int) $this->input('resume_id'), is_string($coverLetter) ? $coverLetter : null, $answers);
    }
}
