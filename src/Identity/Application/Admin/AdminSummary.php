<?php

namespace Src\Identity\Application\Admin;

use DateTimeImmutable;
use Src\Identity\Domain\AccountStatus;

final readonly class AdminSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $role,
        public AccountStatus $status,
        public bool $hasTwoFactor,
        public ?DateTimeImmutable $lastLoginAt,
    ) {}
}
