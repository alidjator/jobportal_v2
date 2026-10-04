<?php

namespace Src\Platform\Application\Audit;

use DateTimeImmutable;

/** One append-only audit record: who did what to which object, when and from where. Metadata must not hold sensitive values. */
final readonly class AuditEntry
{
    /** @param  array<string, scalar|null>  $metadata */
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
    ) {}
}
