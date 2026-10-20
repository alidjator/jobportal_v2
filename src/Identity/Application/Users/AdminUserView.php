<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Domain\User;

/**
 * An account as an admin holding `users.view` sees it, with the reason its email address is suppressed (null when it is not). `onlyCompanyAdmin` is filled when one account is opened (not in the
 * list): true when the account is the only company admin of its company, so the admin knows the stakes of a reset; null for any other account.
 */
final readonly class AdminUserView
{
    public function __construct(
        public User $user,
        public ?string $emailSuppressionReason,
        public ?bool $onlyCompanyAdmin = null,
    ) {}
}
