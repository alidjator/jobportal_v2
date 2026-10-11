<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Interface\Http\Requests\AssessmentInvitationFields;

/** The test to send, when the candidate may start (default: now), the due time and the assessors. Whether the window and the people are acceptable is a use case rule. */
final class SendInvitationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return AssessmentInvitationFields::rules();
    }

    public function toInput(): AssessmentInvitationInput
    {
        return AssessmentInvitationFields::input($this->validated());
    }
}
