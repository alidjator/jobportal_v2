<?php

namespace Src\Recruitment\Interface\Http\Requests\JobManagement;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationGroupUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['group' => 'required|integer'];
    }
}
