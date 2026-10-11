<?php

namespace Src\Platform\Application\Audit;

use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Clock;

/**
 * Scheduled job: deletes audit entries older than 12 months, in batches of 1,000, and records one `audit.purged` entry (actor
 * `system`, with the number of deleted entries and the cutoff) so the deletion itself leaves a trace. That entry is itself
 * purged 12 months later. When nothing was old enough no entry is written. Safe to run again at any time.
 */
final readonly class PurgeExpiredAuditLogs
{
    public const string RETENTION = '-12 months';

    public const int BATCH_SIZE = 1000;

    public function __construct(
        private AuditLogRepository $audit,
        private Clock $clock,
    ) {}

    /** @return int the number of entries deleted */
    public function execute(): int
    {
        $now = $this->clock->now();
        $cutoff = $now->modify(self::RETENTION);
        $deleted = 0;

        do {
            $batch = $this->audit->purgeOlderThan($cutoff, self::BATCH_SIZE);
            $deleted += $batch;
        } while ($batch === self::BATCH_SIZE);

        if ($deleted > 0) {
            $this->audit->append(new AuditEntry(
                AuditActorType::System, null, AuditAction::AUDIT_PURGED, null, null,
                ['deleted' => $deleted, 'older_than' => $cutoff->format(DATE_ATOM)], null, null, $now,
            ));
        }

        return $deleted;
    }
}
