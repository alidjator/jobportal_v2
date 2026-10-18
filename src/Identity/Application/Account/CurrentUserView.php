<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\User;

/** The signed-in account with whether its email address still receives email (it does not once the address bounced or complained). */
final readonly class CurrentUserView
{
    public function __construct(
        public User $user,
        public bool $emailDeliverable,
    ) {}
}
