<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The candidate's consent to the terms of the test. Whether it is given is a use case rule, so a missing or false value gets the same error. */
final class StartAttemptRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['consent' => ['nullable', 'boolean']];
    }

    public function consent(): bool
    {
        return $this->boolean('consent');
    }
}
