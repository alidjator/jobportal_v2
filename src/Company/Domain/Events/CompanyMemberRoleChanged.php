<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A member's role changed. `newPermissions` are the permissions the member holds afterwards (a demotion gives the Recruiter preset unless the request named others). */
final readonly class CompanyMemberRoleChanged implements DomainEvent
{
    /** @param  list<string>  $newPermissions */
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $oldRole,
        public string $newRole,
        public array $newPermissions,
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
