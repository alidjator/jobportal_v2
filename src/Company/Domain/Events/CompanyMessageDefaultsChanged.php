<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class CompanyMessageDefaultsChanged implements DomainEvent
{
    /** @param  list<string>  $scenarios  the scenarios whose default changed (texts are not audited) */
    public function __construct(
        public int $companyId,
        public array $scenarios,
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
