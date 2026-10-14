<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Company\CompanyPermission;

final class InviteMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::enum(CompanyRole::class)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::enum(CompanyPermission::class)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function role(): CompanyRole
    {
        return CompanyRole::from($this->string('role')->toString());
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_values(array_map('strval', $this->array('permissions')));
    }
}
