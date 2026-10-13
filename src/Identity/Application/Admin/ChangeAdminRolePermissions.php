<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Domain\Events\AdminRolePermissionsChanged;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The operator replaces the permissions of an admin role (console only). `plan()` shows what would change so the command can ask first; `execute()`
 * applies it. `super_admin` is kept complete by `identity:sync-permissions` and cannot be edited here.
 */
final readonly class ChangeAdminRolePermissions
{
    public function __construct(
        private AdminDirectory $directory,
        private AdminRolePermissions $permissions,
        private AdminRbacWriter $rbac,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<string>  $permissionNames
     *
     * @throws EntityNotFound when the role does not exist
     * @throws StateConflict `role-protected` for `super_admin`
     * @throws BusinessRuleViolation `permissions-required` or `unknown-permission`
     */
    public function plan(string $name, array $permissionNames): AdminRolePermissionChange
    {
        if ($name === AdminRoles::SUPER_ADMIN) {
            throw new StateConflict('The super_admin role holds every permission and cannot be edited.', 'role-protected');
        }

        $role = $this->directory->role($name) ?? throw EntityNotFound::of('Role', $name);
        $wanted = $this->permissions->validated($permissionNames);

        return new AdminRolePermissionChange(
            array_values(array_diff($wanted, $role->permissions)),
            array_values(array_diff($role->permissions, $wanted)),
        );
    }

    /**
     * @param  list<string>  $permissionNames
     *
     * @throws EntityNotFound
     * @throws StateConflict
     * @throws BusinessRuleViolation
     */
    public function execute(string $name, array $permissionNames, string $operator): AdminRolePermissionChange
    {
        $change = $this->plan($name, $permissionNames);

        if ($change->isEmpty()) {
            return $change;
        }

        $wanted = $this->permissions->validated($permissionNames);

        $this->transactions->run(function () use ($name, $wanted): void {
            $this->permissions->synchronise();
            $this->rbac->syncRole($name, $wanted);
        });

        $this->events->dispatch([new AdminRolePermissionsChanged($name, $change->added, $change->removed, $operator, $this->clock->now())]);

        return $change;
    }
}
