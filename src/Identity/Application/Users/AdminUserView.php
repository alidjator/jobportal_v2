<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Domain\User;

/**
 * An account as an admin holding `users.view` sees it, with the reason its email address is suppressed (null when it is not). `onlyCompanyAdmin` is filled when one account is opened (not in the
 * list): true when the account is the only company admin of its company, so the admin knows the stakes of a reset; null for any other account. `activationEmails` (the activation emails the account got, oldest first) is filled the same way, when one account is opened.
 */
final readonly class AdminUserView
{
    /** @param  list<array{kind: string, batch: string, sentAt: \DateTimeImmutable}>|null  $activationEmails */
    public function __construct(
        public User $user,
        public ?string $emailSuppressionReason,
        public ?bool $onlyCompanyAdmin = null,
        public ?array $activationEmails = null,
    ) {}
}
