<?php

namespace Src\Recruitment\Interface\Http\Requests\JobManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\JobManagement\JobStatus;

class ChangeJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slug' => 'required|string',
            'status' => ['required', Rule::in(JobStatus::values())],
        ];
    }
}
