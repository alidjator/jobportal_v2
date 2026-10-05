<?php

namespace Src\Shared\Application\Accounts;

use DateTimeImmutable;

/** A freshly signed-in public account: the plain token (shown to the client once) and the account fields a sign-in response carries. */
final readonly class AccountSession
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public int $userId,
        public string $type,
        public string $name,
        public string $email,
        public bool $emailVerified,
        public string $status,
        public string $locale,
    ) {}
}
