<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class CompanyEmailDomainsChanged implements DomainEvent
{
    public function __construct(
        public int $companyId,
        /** @var ?list<string> */ public ?array $before,
        /** @var ?list<string> */ public ?array $after,
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
