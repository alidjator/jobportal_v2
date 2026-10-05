<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\CompanyRole;

final class InviteMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::enum(CompanyRole::class)],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function role(): CompanyRole
    {
        return CompanyRole::from($this->string('role')->toString());
    }
}
