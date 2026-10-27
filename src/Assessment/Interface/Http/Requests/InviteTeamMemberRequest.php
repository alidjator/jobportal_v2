<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\PsychologistRole;

final class InviteTeamMemberRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'role' => ['sometimes', 'string', Rule::enum(PsychologistRole::class)],
        ];
    }

    public function role(): PsychologistRole
    {
        return $this->has('role') ? PsychologistRole::from($this->string('role')->toString()) : PsychologistRole::Member;
    }
}
