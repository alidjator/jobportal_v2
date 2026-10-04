<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;

/** A freshly issued code: the plain text exists only here, long enough to be sent, and is never stored. */
final readonly class IssuedVerificationCode
{
    public function __construct(
        public string $code,
        public DateTimeImmutable $expiresAt,
    ) {}
}
