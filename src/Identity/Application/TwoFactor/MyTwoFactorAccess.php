<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The account behind `me/two-factor`: a company member's. A job seeker has no second factor, and a psychologist handles theirs at the provider endpoints. */
final readonly class MyTwoFactorAccess
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     */
    public function user(Actor $actor): User
    {
        if ($actor->kind !== ActorKind::CompanyMember) {
            throw new AccessDenied('Only a company member has a second factor here.', 'not-a-company-member');
        }

        return $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);
    }
}
