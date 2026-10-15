<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Shared\Application\Organization\GroupPermission;

/** The permissions of a group role: a list of at least one name of the group permission catalog. */
final class SetGroupRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1', 'max:'.count(GroupPermission::cases())],
            'permissions.*' => ['string', 'distinct', Rule::enum(GroupPermission::class)],
        ];
    }

    /** @return list<GroupPermission> */
    public function permissions(): array
    {
        return array_map(static fn (mixed $name): GroupPermission => GroupPermission::from((string) $name), array_values((array) $this->input('permissions')));
    }
}
