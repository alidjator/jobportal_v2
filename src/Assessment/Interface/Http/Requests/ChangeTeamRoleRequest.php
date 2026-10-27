<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\PsychologistRole;

final class ChangeTeamRoleRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['role' => ['required', 'string', Rule::enum(PsychologistRole::class)]];
    }

    public function role(): PsychologistRole
    {
        return PsychologistRole::from($this->string('role')->toString());
    }
}
