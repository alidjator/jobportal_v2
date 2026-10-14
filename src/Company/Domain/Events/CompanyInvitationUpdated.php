<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class CompanyInvitationUpdated implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $invitationId,
        public ?string $oldRole,
        public string $role,
        /** @var ?list<string> */ public ?array $permissions,
        public int $updatedByUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
