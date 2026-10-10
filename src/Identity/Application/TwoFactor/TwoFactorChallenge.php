<?php

namespace Src\Identity\Application\TwoFactor;

final readonly class TwoFactorChallenge
{
    public function __construct(public int $id, public int $adminId) {}
}
