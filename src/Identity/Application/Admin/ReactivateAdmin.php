<?php

namespace Src\Identity\Application\Admin;

use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\AdminReactivated;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The operator reactivates a suspended administrator (console only); they sign in again with their password and second factor. */
final readonly class ReactivateAdmin
{
    public function __construct(
        private AdminRepository $admins,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return int the administrator's id
     *
     * @throws EntityNotFound when no administrator has the email address
     * @throws StateConflict `admin-not-suspended`
     */
    public function execute(string $email, string $operator): int
    {
        $admin = $this->admins->findByEmail(mb_strtolower(trim($email))) ?? throw EntityNotFound::of('Admin', $email);

        $admin->reactivate();
        $this->admins->save($admin);

        $this->events->dispatch([new AdminReactivated((int) $admin->id, $operator, $this->clock->now())]);

        return (int) $admin->id;
    }
}
