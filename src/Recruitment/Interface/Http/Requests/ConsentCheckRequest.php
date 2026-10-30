<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The candidate's consent to a check: `agree` must be true. */
final class ConsentCheckRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['agree' => ['required', 'accepted']];
    }
}
