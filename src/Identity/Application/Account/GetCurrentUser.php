<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetCurrentUser
{
    public function __construct(private UserRepository $users) {}

    /** @throws EntityNotFound when the actor's account no longer exists */
    public function execute(Actor $actor): User
    {
        return $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);
    }
}
