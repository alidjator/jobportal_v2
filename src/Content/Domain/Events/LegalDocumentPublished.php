<?php

namespace Src\Content\Domain\Events;

use DateTimeImmutable;
use Src\Content\Domain\LegalDocumentType;
use Src\Shared\Domain\DomainEvent;

/** A new version became the active one: every user must accept it before using the API (terms gate). */
final readonly class LegalDocumentPublished implements DomainEvent
{
    public function __construct(
        public int $documentId,
        public LegalDocumentType $type,
        public int $version,
        public ?int $adminId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
