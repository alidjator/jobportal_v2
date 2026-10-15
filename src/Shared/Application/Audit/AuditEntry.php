<?php

namespace Src\Shared\Application\Audit;

use DateTimeImmutable;

/**
 * One append-only audit record: who did what to which object, when and from where. Metadata must not hold sensitive values. `companyId` is the company
 * the entry belongs to for the company activity log; left empty, the recorder fills it (the company the acting member belongs to, or the subject when
 * that is a company).
 */
final readonly class AuditEntry
{
    /** @param  array<string, mixed>  $metadata values that can be written as JSON (scalars, lists) */
    public function __construct(
        public AuditActorType $actorType,
        public ?int $actorId,
        public string $action,
        public ?string $subjectType,
        public ?int $subjectId,
        public array $metadata,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $occurredAt,
        public ?int $companyId = null,
    ) {}
}
