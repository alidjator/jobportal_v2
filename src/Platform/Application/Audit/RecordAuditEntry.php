<?php

namespace Src\Platform\Application\Audit;

/** The single way to write the audit log. Called by listeners of other contexts' events and, later, by use cases. */
final readonly class RecordAuditEntry
{
    public function __construct(private AuditLogRepository $audit) {}

    public function execute(AuditEntry $entry): void
    {
        $this->audit->append($entry);
    }
}
