<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRole;

/** The role and permissions a preset stands for. A company admin has no permissions of their own (they hold every one). */
final readonly class ResolvedPreset
{
    /** @param  list<string>  $permissions  catalog names; empty for a company admin */
    public function __construct(public CompanyRole $role, public array $permissions) {}

    /** @return ?list<string> null for a company admin, as the invitation stores it */
    public function permissionsOrNull(): ?array
    {
        return $this->role === CompanyRole::CompanyAdmin ? null : $this->permissions;
    }
}
