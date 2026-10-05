<?php

namespace Src\Company\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A document type became required or optional, or active or inactive, so the verification of companies may change. Edits of names or order do not raise it. */
final readonly class DocumentTypeVerificationRulesChanged implements DomainEvent
{
    public function __construct(
        public int $typeId,
        public string $slug,
        public bool $wasRequired,
        public bool $isRequired,
        public bool $wasActive,
        public bool $isActive,
        public int $adminId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
