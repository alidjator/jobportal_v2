<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Domain\Events\AdminRoleDeleted;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The operator deletes an admin role that no administrator holds (console only). `super_admin` cannot be deleted. */
final readonly class DeleteAdminRole
{
    public function __construct(
        private AdminDirectory $directory,
        private AdminRbacWriter $rbac,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound when the role does not exist
     * @throws StateConflict `role-protected` for `super_admin`, `role-in-use` while an administrator holds it
     */
    public function execute(string $name, string $operator): void
    {
        if ($name === AdminRoles::SUPER_ADMIN) {
            throw new StateConflict('The super_admin role cannot be deleted.', 'role-protected');
        }

        $role = $this->directory->role($name) ?? throw EntityNotFound::of('Role', $name);

        if ($role->adminCount > 0) {
            throw new StateConflict("{$role->adminCount} administrator(s) hold the role {$name}; give them another role first.", 'role-in-use');
        }

        $this->rbac->deleteRole($name);

        $this->events->dispatch([new AdminRoleDeleted($name, $operator, $this->clock->now())]);
    }
}
