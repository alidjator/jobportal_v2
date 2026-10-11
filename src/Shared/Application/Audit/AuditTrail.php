<?php

namespace Src\Shared\Application\Audit;

/** The single way to write the append-only audit log. Each context's own listener (or use case) calls it; the Platform context stores the entries. */
interface AuditTrail
{
    public function record(AuditEntry $entry): void;
}
