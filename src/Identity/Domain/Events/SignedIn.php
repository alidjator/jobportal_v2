<?php

namespace Src\Identity\Domain\Events;

use DateTimeImmutable;
use Src\Identity\Domain\AccountScope;
use Src\Shared\Domain\DomainEvent;

final readonly class SignedIn implements DomainEvent
{
    public function __construct(
        public AccountScope $scope,
        public int $accountId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
        public bool $firstSignIn = false,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
