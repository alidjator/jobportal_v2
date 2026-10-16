<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A group member read applicants of a tenant of their group under `group.recruitment.view`: `request` is what was read (method and path, without the query). */
final readonly class GroupRecruitmentRead implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $companyId,
        public int $userId,
        public string $request,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
