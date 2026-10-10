<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Identity\Domain\AccountScope;
use Src\Shared\Domain\DomainEvent;

/** A signed-in account revoked every one of its tokens, the one in use included. */
final readonly class SignedOutEverywhere implements DomainEvent
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
