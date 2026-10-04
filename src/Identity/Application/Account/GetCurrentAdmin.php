<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\AdminRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetCurrentAdmin
{
    public function __construct(
        private AdminRepository $admins,
        private AdminAccessReader $access,
    ) {}

    /** @throws EntityNotFound when the actor's account no longer exists */
    public function execute(Actor $actor): CurrentAdmin
    {
        $admin = $this->admins->findById((int) $actor->id) ?? throw EntityNotFound::of('Admin', $actor->id);

        return new CurrentAdmin($admin, $this->access->forAdmin((int) $admin->id));
    }
}
