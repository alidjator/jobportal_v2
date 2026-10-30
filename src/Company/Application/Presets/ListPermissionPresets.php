<?php

namespace Src\Company\Application\Presets;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\PermissionPreset;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The permission presets the company saved, by name. Company admins only: they are the ones who give access. */
final readonly class ListPermissionPresets
{
    public function __construct(private CompanyAdminGuard $guard, private PermissionPresetRepository $presets) {}

    /**
     * @return list<PermissionPreset>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        return $this->presets->ofCompany((int) $this->guard->company($actor)->id);
    }
}
