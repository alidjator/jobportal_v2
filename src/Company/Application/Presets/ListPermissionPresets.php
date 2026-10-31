<?php

namespace Src\Company\Application\Presets;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The permission presets the company saved, by name, each with the number of members who hold its permissions. Company admins only: they are the ones who give access. */
final readonly class ListPermissionPresets
{
    public function __construct(private CompanyAdminGuard $guard, private PermissionPresetRepository $presets, private PresetHolders $holders) {}

    /**
     * @return list<PermissionPresetUsage>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $companyId = (int) $this->guard->company($actor)->id;

        return array_map(
            fn ($preset): PermissionPresetUsage => new PermissionPresetUsage($preset, count($this->holders->userIds($companyId, $preset->permissions))),
            $this->presets->ofCompany($companyId),
        );
    }
}
