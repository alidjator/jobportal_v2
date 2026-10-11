<?php

namespace Src\Identity\Application\TwoFactor;

final readonly class UserTwoFactorChallenge
{
    public function __construct(public int $id, public int $userId) {}
}
