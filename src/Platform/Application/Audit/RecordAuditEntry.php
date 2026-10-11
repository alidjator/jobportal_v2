<?php

namespace Src\Platform\Application\Audit;

use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;

/** Writes the audit log: the Platform implementation of the Shared `AuditTrail` port. */
final readonly class RecordAuditEntry implements AuditTrail
{
    public function __construct(private AuditLogRepository $audit) {}

    public function record(AuditEntry $entry): void
    {
        $this->audit->append($entry);
    }
}
