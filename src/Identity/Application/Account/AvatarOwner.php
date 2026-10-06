<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds the account whose picture an avatar action is about: the signed-in job seeker or company member themselves. */
final readonly class AvatarOwner
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws AccessDenied `public-account-required` for anyone but a job seeker or a company member
     * @throws EntityNotFound when the account no longer exists
     */
    public function user(Actor $actor): User
    {
        if (($actor->kind !== ActorKind::JobSeeker && $actor->kind !== ActorKind::CompanyMember) || $actor->id === null) {
            throw new AccessDenied('Only a job seeker or a company member has a profile picture.', 'public-account-required');
        }

        return $this->users->findById($actor->id) ?? throw EntityNotFound::of('User', $actor->id);
    }
}
