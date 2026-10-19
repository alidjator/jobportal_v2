<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A work location was created, edited, made main, deactivated, activated or deleted. `$change` is one of `created`, `updated`, `main_set`, `deactivated`, `activated` and `deleted`; `$fields` names what an edit changed. */
final readonly class CompanyLocationChanged implements DomainEvent
{
    public function __construct(
        public int $companyId,
        public int $locationId,
        public string $change,
        /** @var list<string> */ public array $fields,
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
