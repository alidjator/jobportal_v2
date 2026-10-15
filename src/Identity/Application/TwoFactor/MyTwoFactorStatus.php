<?php

namespace Src\Identity\Application\TwoFactor;

/** The second factor of the signed-in company member: switched on, required of them, and how many recovery codes are left. */
final readonly class MyTwoFactorStatus
{
    public function __construct(
        public bool $enabled,
        public bool $required,
        public int $recoveryCodesLeft,
    ) {}
}
