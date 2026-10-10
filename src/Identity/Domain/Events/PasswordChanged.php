<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Identity\Domain\AccountScope;
use Src\Shared\Domain\DomainEvent;

/** A signed-in account changed its own password; every other token of the account was revoked. */
final readonly class PasswordChanged implements DomainEvent
{
    public function __construct(
        public AccountScope $scope,
        public int $accountId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
