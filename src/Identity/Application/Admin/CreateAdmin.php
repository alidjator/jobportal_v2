<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Domain\Admin;
use Src\Identity\Domain\AdminRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Creates an administrator with a role. Used by the operator command that creates the first admin; managing admins through the
 * API comes later. Run `identity:sync-permissions` first so the role exists with its permissions.
 */
final readonly class CreateAdmin
{
    public function __construct(
        private AdminRepository $admins,
        private AdminRbacWriter $rbac,
        private PasswordHasher $hasher,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws StateConflict `email-already-registered` when an administrator already uses the email */
    public function execute(string $name, string $email, string $password, string $role = AdminRoles::SUPER_ADMIN): Admin
    {
        $email = mb_strtolower(trim($email));

        if ($this->admins->findByEmail($email) !== null) {
            throw new StateConflict('An administrator with this email already exists.', 'email-already-registered');
        }

        $admin = Admin::create(trim($name), $email, $this->hasher->make($password), $this->clock->now());

        return $this->transactions->run(function () use ($admin, $role): Admin {
            $admin = $this->admins->save($admin);
            $this->rbac->assignRole((int) $admin->id, $role);

            return $admin;
        });
    }
}
