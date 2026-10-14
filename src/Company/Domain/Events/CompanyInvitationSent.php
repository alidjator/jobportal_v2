<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An invitation was sent, by a company admin (`invitedByUserId`) or by a platform admin (`invitedByAdminId`); exactly one of them is set. */
final readonly class CompanyInvitationSent implements DomainEvent
{
    /** @param  ?list<string>  $permissions  the permissions chosen for a member; null for a company admin */
    public function __construct(
        public int $companyId,
        public int $invitationId,
        public string $role,
        public ?array $permissions,
        public ?int $invitedByUserId,
        public ?int $invitedByAdminId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
