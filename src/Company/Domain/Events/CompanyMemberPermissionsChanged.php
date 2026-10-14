<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The permissions of a member changed while their role stayed `member`. `before` and `after` are permission names; the audit entry derives what was added and removed. */
final readonly class CompanyMemberPermissionsChanged implements DomainEvent
{
    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public function __construct(
        public int $companyId,
        public int $userId,
        public array $before,
        public array $after,
        public int $changedByUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
