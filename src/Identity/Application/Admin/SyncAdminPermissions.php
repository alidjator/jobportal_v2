<?php

namespace Src\Identity\Application\Admin;

use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\TransactionManager;

/**
 * Brings the permissions in the database in step with the catalog registered by the contexts, and gives `super_admin` all of
 * them. Idempotent: run it after every deployment that adds a permission.
 */
final readonly class SyncAdminPermissions
{
    public function __construct(
        private PermissionCatalog $catalog,
        private AdminRbacWriter $rbac,
        private TransactionManager $transactions,
    ) {}

    /** @return int the number of permissions in the catalog */
    public function execute(): int
    {
        $definitions = $this->catalog->all();

        $this->transactions->run(function () use ($definitions): void {
            $this->rbac->syncPermissions($definitions);
            $this->rbac->syncRole(AdminRoles::SUPER_ADMIN, array_map(fn ($definition) => $definition->name, $definitions));
        });

        return count($definitions);
    }
}
