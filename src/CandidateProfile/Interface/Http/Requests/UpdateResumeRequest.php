<?php

namespace Src\CandidateProfile\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'resume_file' => 'sometimes|file|mimes:pdf|max:5120',
        ];
    }
}
