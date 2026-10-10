<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Shared\Application\Clock;

/** Deletes the sign-in challenges that expired or were used (nightly). */
final readonly class PurgeTwoFactorChallenges
{
    public function __construct(private TwoFactorChallenges $challenges, private Clock $clock) {}

    public function execute(): int
    {
        return $this->challenges->purge($this->clock->now());
    }
}
