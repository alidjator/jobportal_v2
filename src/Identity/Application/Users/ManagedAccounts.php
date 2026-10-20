<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The accounts a platform admin holding `users.manage` may act on: job seekers and company members (a psychologist has its own flow, an admin is managed on the console). */
final readonly class ManagedAccounts
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the account does not exist or was anonymised
     * @throws StateConflict `account-not-correctable` for a psychologist
     */
    public function find(Actor $actor, int $userId): User
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can manage an account.');
        }

        $user = $this->users->findById($userId);

        if ($user === null || $user->anonymizedAt() !== null) {
            throw EntityNotFound::of('User', $userId);
        }

        if ($user->isPsychologist()) {
            throw new StateConflict('Psychologist accounts are managed through the assessment console, not here.', 'account-not-correctable');
        }

        return $user;
    }

    /** @throws StateConflict `account-not-active` for a suspended or deactivated account */
    public function assertActive(User $user): void
    {
        if (! $user->status()->isActive()) {
            throw new StateConflict('The account is not active.', 'account-not-active');
        }
    }
}
