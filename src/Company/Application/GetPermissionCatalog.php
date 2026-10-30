<?php

namespace Src\Company\Application;

use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\MemberPreset;
use Src\Company\Domain\PermissionPreset;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The permission catalog with its groups and the presets, for the invitation and edit dialogs. Any company member may read it; a company admin also gets the presets the company saved
 * (`id` and `name` set, `preset` is `saved_{id}`), which a member never needs.
 */
final readonly class GetPermissionCatalog
{
    public function __construct(private PermissionPresetRepository $saved) {}

    /**
     * @return array{groups: list<array{group: string, permissions: list<array{permission: string, requires_view: bool, requires_review: bool}>}>, presets: list<array{preset: string, role: string, permissions: list<string>, id: int|null, name: string|null}>}
     *
     * @throws AccessDenied `not-a-company-member`
     */
    public function execute(Actor $actor): array
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can read the permissions.', 'not-a-company-member');
        }

        $groups = [];

        foreach (CompanyPermission::cases() as $permission) {
            $groups[$permission->group()][] = ['permission' => $permission->value, 'requires_view' => $permission->requiresView(), 'requires_review' => $permission->requiresReview()];
        }

        return [
            'groups' => array_map(static fn (string $group, array $permissions): array => ['group' => $group, 'permissions' => $permissions], array_keys($groups), $groups),
            'presets' => [
                ...array_map(static fn (MemberPreset $preset): array => [
                    'preset' => $preset->value, 'role' => $preset->role()->value, 'permissions' => $preset->permissionNames(), 'id' => null, 'name' => null,
                ], MemberPreset::cases()),
                ...($actor->isCompanyAdmin() ? array_map(static fn (PermissionPreset $preset): array => [
                    'preset' => 'saved_'.$preset->id, 'role' => CompanyRole::Member->value, 'permissions' => $preset->permissions, 'id' => $preset->id, 'name' => $preset->name,
                ], $this->saved->ofCompany($actor->membership->companyId)) : []),
            ],
        ];
    }
}
