<?php

namespace Src\Identity\Application\Admin;

use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Checks the permissions an operator gives a role against the catalog, and makes sure the catalog exists in the database before a role uses it. */
final readonly class AdminRolePermissions
{
    public function __construct(
        private PermissionCatalog $catalog,
        private AdminRbacWriter $rbac,
    ) {}

    /**
     * @param  list<string>  $names
     * @return list<string> without duplicates, sorted
     *
     * @throws BusinessRuleViolation `permissions-required` or `unknown-permission`
     */
    public function validated(array $names): array
    {
        $names = array_values(array_unique(array_map('trim', $names)));
        sort($names);

        if ($names === []) {
            throw new BusinessRuleViolation('Give the role at least one permission.', 'permissions-required');
        }

        $known = array_map(fn ($definition) => $definition->name, $this->catalog->all());
        $unknown = array_values(array_diff($names, $known));

        if ($unknown !== []) {
            throw new BusinessRuleViolation('Unknown permission: :permissions.', 'unknown-permission', parameters: ['permissions' => implode(', ', $unknown)]);
        }

        return $names;
    }

    /** Creates the catalog's permissions that are missing (as `identity:sync-permissions` does), so a role can hold one added by the last deployment. */
    public function synchronise(): void
    {
        $this->rbac->syncPermissions($this->catalog->all());
    }
}
