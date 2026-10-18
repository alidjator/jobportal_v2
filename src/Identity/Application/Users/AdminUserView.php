<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Domain\User;

/** An account as an admin holding `users.view` sees it, with the reason its email address is suppressed (null when it is not). */
final readonly class AdminUserView
{
    public function __construct(
        public User $user,
        public ?string $emailSuppressionReason,
    ) {}
}
