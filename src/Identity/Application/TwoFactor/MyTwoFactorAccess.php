<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The account behind `me/two-factor`: a company member's. A job seeker has no second factor. A psychologist always has one (it is enrolled at their first sign-in) and may read its status and renew its recovery
 * codes (`account()`), but enrolling and switching it off stay with company members (`user()`).
 */
final readonly class MyTwoFactorAccess
{
    public function __construct(private UserRepository $users) {}

    /**
     * The account of a company member or of a psychologist, for the status and the recovery codes of the second factor.
     *
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     */
    public function account(Actor $actor): User
    {
        if ($actor->kind !== ActorKind::CompanyMember && $actor->kind !== ActorKind::Psychologist) {
            throw new AccessDenied('Only a company member or a psychologist has a second factor here.', 'not-a-company-member');
        }

        return $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);
    }

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
