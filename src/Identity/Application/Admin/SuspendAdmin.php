<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\AdminSuspended;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The operator suspends an administrator (console only): every token is revoked at once and sign-in is refused until reactivation. */
final readonly class SuspendAdmin
{
    public function __construct(
        private AdminRepository $admins,
        private LastSuperAdminGuard $lastSuperAdmin,
        private AccessTokenIssuer $tokens,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return int the administrator's id
     *
     * @throws EntityNotFound when no administrator has the email address
     * @throws StateConflict `admin-not-active`, `last-super-admin`
     */
    public function execute(string $email, string $operator): int
    {
        $admin = $this->admins->findByEmail(mb_strtolower(trim($email))) ?? throw EntityNotFound::of('Admin', $email);
        $adminId = (int) $admin->id;

        $this->lastSuperAdmin->assertNotLast($admin);
        $admin->suspend();

        $this->transactions->run(function () use ($admin, $adminId): void {
            $this->admins->save($admin);
            $this->tokens->revokeAll(Actor::admin($adminId));
        });

        $this->events->dispatch([new AdminSuspended($adminId, $operator, $this->clock->now())]);

        return $adminId;
    }
}
