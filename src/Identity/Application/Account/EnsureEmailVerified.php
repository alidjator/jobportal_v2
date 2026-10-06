<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\UserRepository;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Refuses an action that affects other parties (such as applying to a job) while the account's email is not verified. */
final readonly class EnsureEmailVerified
{
    public function __construct(private UserRepository $users) {}

    /** @throws StateConflict `email-not-verified` */
    public function execute(int $userId): void
    {
        $user = $this->users->findById($userId);

        if ($user === null || ! $user->isEmailVerified()) {
            throw new StateConflict('Verify your email address before continuing.', 'email-not-verified');
        }
    }
}
