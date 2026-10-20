<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\EmailChangeRepository;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetCurrentUser
{
    public function __construct(private UserRepository $users, private EmailSuppressions $suppressions, private EmailChangeRepository $changes, private Clock $clock) {}

    /** @throws EntityNotFound when the actor's account no longer exists */
    public function execute(Actor $actor): CurrentUserView
    {
        $user = $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);

        $pending = $this->changes->findByUser((int) $user->id);

        return new CurrentUserView($user, $this->suppressions->reasons([$user->email()]) === [], $pending?->isUsable($this->clock->now()) ? $pending : null);
    }
}
