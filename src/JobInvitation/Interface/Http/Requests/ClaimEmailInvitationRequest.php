<?php

namespace Src\JobInvitation\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The person agrees to be considered for the job: `agree` must be true. */
final class ClaimEmailInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['agree' => ['required', 'accepted']];
    }
}
