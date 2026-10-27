<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Validation\Rule;
use Src\Assessment\Domain\PsychologistRole;

final class UpdatePsychologistCredentialsRequest extends PsychologistCredentialsRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...$this->credentialRules(), 'role' => ['sometimes', 'string', Rule::enum(PsychologistRole::class)]];
    }

    public function role(): ?PsychologistRole
    {
        return $this->has('role') ? PsychologistRole::from($this->string('role')->toString()) : null;
    }
}
