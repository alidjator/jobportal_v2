<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Company\CompanyPermission;

final class UpdateMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'string', Rule::enum(CompanyRole::class)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::enum(CompanyPermission::class)],
        ];
    }

    public function role(): ?CompanyRole
    {
        return $this->has('role') ? CompanyRole::from($this->string('role')->toString()) : null;
    }

    /** @return ?list<string> */
    public function permissions(): ?array
    {
        return $this->has('permissions') ? array_values(array_map('strval', $this->array('permissions'))) : null;
    }
}
