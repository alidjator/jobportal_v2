<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\CompanyRole;

final class ChangeMemberRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['role' => ['required', 'string', Rule::enum(CompanyRole::class)]];
    }

    public function role(): CompanyRole
    {
        return CompanyRole::from($this->string('role')->toString());
    }
}
