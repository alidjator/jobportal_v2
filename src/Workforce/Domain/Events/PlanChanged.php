<?php

namespace Src\Workforce\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * A manpower plan was `created`, `updated`, `submitted`, `revised` (a new version was started from the one in force), `approved`, `rejected`, `returned` or `superseded`.
 * `actorId` is null when the system did it; `companyId` is the tenant of the legal entity, null when it has none.
 */
final readonly class PlanChanged implements DomainEvent
{
    public function __construct(
        public int $planId,
        public int $legalEntityId,
        public int $groupId,
        public ?int $companyId,
        public int $fiscalYear,
        public int $version,
        public string $change,
        public ?int $actorId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
        public ?string $reason = null,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
