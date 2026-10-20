<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\EmailChange;
use Src\Identity\Domain\User;

/** The signed-in account with whether its email address still receives email (it does not once the address bounced or complained) and its pending email change, when it has a usable one. */
final readonly class CurrentUserView
{
    public function __construct(
        public User $user,
        public bool $emailDeliverable,
        public ?EmailChange $pendingEmailChange = null,
    ) {}
}
