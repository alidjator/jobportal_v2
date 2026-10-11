<?php

namespace Src\Assessment\Interface\Http\Requests;

final class UpdatePsychologistCredentialsRequest extends PsychologistCredentialsRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->credentialRules();
    }
}
