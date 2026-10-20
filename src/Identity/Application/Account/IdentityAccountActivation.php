<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\AccountActivation;

/** Gives the other contexts the imported accounts that have not signed in yet, one read per list. */
final readonly class IdentityAccountActivation implements AccountActivation
{
    public function __construct(private UserRepository $users) {}

    public function heldAmong(array $userIds): array
    {
        return $userIds === [] ? [] : $this->users->findHeldIds($userIds);
    }
}
