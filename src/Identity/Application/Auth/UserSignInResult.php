<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;
use Src\Identity\Domain\User;

final readonly class UserSignInResult
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public User $user,
    ) {}
}
