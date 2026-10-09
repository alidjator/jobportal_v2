<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\ApplyToJobInput;
use Src\Shared\Application\Applicant\GeneratedCvSpec;

/**
 * The resume (or a CV to generate instead: exactly one of `resume_id` and `cv`, which the use case enforces: `cv: {}` is a CV with the default choices), the optional cover letter and the answers of an application. Whether the
 * answers fit the job's questions is a use case rule.
 */
final class ApplyToJobRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'resume_id' => ['nullable', 'integer', 'min:1'],
            'cv' => ['nullable', 'array'],
            'cv.template' => ['nullable', 'string', 'max:30'],
            'cv.language' => ['nullable', 'string', 'in:id,en'],
            'cv.include' => ['nullable', 'array'],
            'cv.include.*' => ['boolean'],
            'cover_letter' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:30'],
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

        $resumeId = $this->input('resume_id');
        $cv = $this->input('cv');

        return new ApplyToJobInput(
            $resumeId === null ? null : (int) $resumeId,
            is_string($coverLetter) ? $coverLetter : null,
            $answers,
            is_array($cv) ? $this->cvSpec($cv) : null,
            is_string($source = $this->input('source')) ? $source : null,
        );
    }

    /** @param  array<string, mixed>  $cv */
    private function cvSpec(array $cv): GeneratedCvSpec
    {
        $include = [];

        foreach ((array) ($cv['include'] ?? []) as $key => $on) {
            if (filter_var($on, FILTER_VALIDATE_BOOLEAN)) {
                $include[] = (string) $key;
            }
        }

        return new GeneratedCvSpec(
            isset($cv['template']) && is_string($cv['template']) ? $cv['template'] : null,
            isset($cv['language']) && is_string($cv['language']) ? $cv['language'] : null,
            $include,
        );
    }
}
