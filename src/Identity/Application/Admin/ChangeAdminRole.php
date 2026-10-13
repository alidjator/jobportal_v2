<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\AdminRoleChanged;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The operator gives an administrator another role (console only). An administrator holds one role; the change applies to their next request. */
final readonly class ChangeAdminRole
{
    public function __construct(
        private AdminRepository $admins,
        private AdminDirectory $directory,
        private LastSuperAdminGuard $lastSuperAdmin,
        private AdminRbacWriter $rbac,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return int the administrator's id
     *
     * @throws EntityNotFound when the administrator or the role does not exist
     * @throws StateConflict `role-unchanged` when they already hold the role, `last-super-admin`
     */
    public function execute(string $email, string $role, string $operator): int
    {
        $admin = $this->admins->findByEmail(mb_strtolower(trim($email))) ?? throw EntityNotFound::of('Admin', $email);
        $adminId = (int) $admin->id;

        if ($this->directory->role($role) === null) {
            throw EntityNotFound::of('Role', $role);
        }

        $previous = $this->directory->roleOf($adminId);

        if ($previous === $role) {
            throw new StateConflict("The administrator already holds the role {$role}.", 'role-unchanged');
        }

        if ($role !== AdminRoles::SUPER_ADMIN) {
            $this->lastSuperAdmin->assertNotLast($admin);
        }

        $this->transactions->run(fn () => $this->rbac->replaceRole($adminId, $role));

        $this->events->dispatch([new AdminRoleChanged($adminId, $previous, $role, $operator, $this->clock->now())]);

        return $adminId;
    }
}
