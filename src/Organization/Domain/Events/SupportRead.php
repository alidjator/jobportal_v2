<?php

namespace Src\Organization\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A platform admin read data of a group under its support access: `request` is what was read (method and path, without the query). */
final readonly class SupportRead implements DomainEvent
{
    public function __construct(
        public int $groupId,
        public int $adminId,
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
