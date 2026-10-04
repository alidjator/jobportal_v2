<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;
use Src\Identity\Domain\Admin;

final readonly class AdminSignInResult
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public Admin $admin,
    ) {}
}
