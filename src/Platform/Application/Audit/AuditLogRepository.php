<?php

namespace Src\Platform\Application\Audit;

use DateTimeImmutable;

/** Append-only: entries are never updated, and the only deletion is the retention purge (`PurgeExpiredAuditLogs`). */
interface AuditLogRepository
{
    public function append(AuditEntry $entry): void;

    /** Deletes up to `$limit` entries that happened before `$cutoff`, and returns how many were deleted. */
    public function purgeOlderThan(DateTimeImmutable $cutoff, int $limit): int;
}
