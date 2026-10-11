<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Shared\Application\Clock;

/** Deletes the sign-in challenges of administrators and psychologists that expired or were used (nightly). */
final readonly class PurgeTwoFactorChallenges
{
    public function __construct(private TwoFactorChallenges $challenges, private UserTwoFactorChallenges $userChallenges, private Clock $clock) {}

    public function execute(): int
    {
        $now = $this->clock->now();

        return $this->challenges->purge($now) + $this->userChallenges->purge($now);
    }
}
