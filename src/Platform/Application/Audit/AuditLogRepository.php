<?php

namespace Src\Platform\Application\Audit;

/** Append-only: there is no update or delete (the only deletion is the scheduled retention purge, written later). */
interface AuditLogRepository
{
    public function append(AuditEntry $entry): void;
}
