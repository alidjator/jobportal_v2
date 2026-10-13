<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Domain\Events\AdminRoleCreated;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The operator creates an admin role holding some of the catalog's permissions (console only; the panel has no admin management). */
final readonly class CreateAdminRole
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
     * @return list<string> the permissions of the new role, sorted
     *
     * @throws BusinessRuleViolation `invalid-role-name`, `permissions-required` or `unknown-permission`
     * @throws StateConflict `role-already-exists`
     */
    public function execute(string $name, array $permissionNames, string $operator): array
    {
        $name = trim($name);

        if (preg_match(AdminRoles::NAME_PATTERN, $name) !== 1) {
            throw new BusinessRuleViolation('A role name is 3 to 50 lowercase letters, digits or underscores, starting with a letter.', 'invalid-role-name');
        }

        if ($this->directory->role($name) !== null) {
            throw new StateConflict("The role {$name} already exists.", 'role-already-exists');
        }

        $permissions = $this->permissions->validated($permissionNames);

        $this->transactions->run(function () use ($name, $permissions): void {
            $this->permissions->synchronise();
            $this->rbac->syncRole($name, $permissions);
        });

        $this->events->dispatch([new AdminRoleCreated($name, $permissions, $operator, $this->clock->now())]);

        return $permissions;
    }
}
