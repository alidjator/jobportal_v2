<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The reason an admin gives when rejecting or taking down a job: required, 5 to 1,000 characters. The company reads it. */
final class JobDecisionReasonRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
