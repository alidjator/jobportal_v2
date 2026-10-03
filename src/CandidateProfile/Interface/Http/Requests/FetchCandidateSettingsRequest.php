<?php

namespace Src\CandidateProfile\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchCandidateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['type' => ['required', 'in:personal,profile,social,contact']];
    }
}
