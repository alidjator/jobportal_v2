<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\PermissionPreset;
use Src\Shared\Application\Company\CompanyPermission;

/** The body that saves or changes a permission preset: a `name` and the `permissions` (catalog names); a change may also ask (`apply_to_members`) to give the new set to the members who hold exactly the old one. The rules between permissions are checked by the domain. */
final class PermissionPresetRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:'.PermissionPreset::NAME_MAX_LENGTH],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::enum(CompanyPermission::class)],
            'apply_to_members' => ['sometimes', 'boolean'],
        ];
    }

    public function presetName(): string
    {
        return $this->string('name')->trim()->toString();
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_values(array_map('strval', $this->array('permissions')));
    }

    public function applyToMembers(): bool
    {
        return $this->boolean('apply_to_members');
    }
}
