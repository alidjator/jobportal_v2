<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;
use Src\Identity\Domain\User;

final readonly class PsychologistSignInResult
{
    /** @param  list<string>  $recoveryCodes the plain recovery codes, only when the second factor was just enrolled */
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public User $user,
        public array $recoveryCodes = [],
    ) {}
}
