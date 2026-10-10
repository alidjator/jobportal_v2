<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;
use Src\Identity\Domain\Admin;

final readonly class AdminSignInResult
{
    /** @param  list<string>  $recoveryCodes the plain recovery codes, only when the second factor was just enrolled */
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public Admin $admin,
        public array $recoveryCodes = [],
    ) {}
}
