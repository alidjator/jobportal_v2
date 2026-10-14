<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The ownership of a company moved to another company admin: by the owner (`byAdminId` null, `fromUserId` the actor) or by a platform admin. */
final readonly class CompanyOwnerTransferred implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public ?int $fromUserId,
        public int $toUserId,
        public ?int $byAdminId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
