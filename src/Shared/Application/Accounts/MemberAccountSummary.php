<?php

namespace Src\Shared\Application\Accounts;

use DateTimeImmutable;

/** What a company's team list or a provider's psychologist list shows about an account. */
final readonly class MemberAccountSummary
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public string $status,
        public ?DateTimeImmutable $lastLoginAt,
    ) {}
}
