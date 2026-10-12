<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The candidate's consent to the terms of the test, and to be recorded when it has video questions. Whether it is given is a use case rule, so a missing or false value gets the same error. */
final class StartAttemptRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['consent' => ['nullable', 'boolean'], 'video_consent' => ['nullable', 'boolean']];
    }

    /** The separate consent to be recorded; it matters only when the test has video questions. */
    public function videoConsent(): bool
    {
        return $this->boolean('video_consent');
    }

    public function consent(): bool
    {
        return $this->boolean('consent');
    }
}
