<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Domain\Admin;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Keeps at least one active `super_admin`, so no operator command can lock the platform out of its own administration. */
final readonly class LastSuperAdminGuard
{
    public function __construct(private AdminDirectory $directory) {}

    /** @throws StateConflict `last-super-admin` when the administrator is the only active `super_admin` */
    public function assertNotLast(Admin $admin): void
    {
        if (! $admin->status()->isActive() || $this->directory->roleOf((int) $admin->id) !== AdminRoles::SUPER_ADMIN) {
            return;
        }

        if ($this->directory->countActiveAdminsWithRole(AdminRoles::SUPER_ADMIN) <= 1) {
            throw new StateConflict('This is the only active super_admin; give another administrator that role first.', 'last-super-admin');
        }
    }
}
