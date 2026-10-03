<?php

namespace Src\Recruitment\Interface\Http\Requests\JobBoard;

use Illuminate\Foundation\Http\FormRequest;

class ApplyJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'resume_id' => ['required'],
            'cover_letter' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resume_id.required' => 'Please select resume',
            'cover_letter.required' => 'Please enter cover letter',
        ];
    }
}
