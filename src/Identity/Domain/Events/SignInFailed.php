<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Identity\Domain\AccountScope;
use Src\Shared\Domain\DomainEvent;

/** A sign-in was refused. `reason` is the error code (`invalid-credentials`, `account-suspended`, `account-deactivated`). */
final readonly class SignInFailed implements DomainEvent
{
    public function __construct(
        public AccountScope $scope,
        public ?int $accountId,
        public string $reason,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
