<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Platform\Application\Audit\AuditLogRepository;
use Src\Shared\Application\Audit\AuditEntry;

final readonly class DatabaseAuditLogRepository implements AuditLogRepository
{
    private const int USER_AGENT_LENGTH = 255;

    public function __construct(private ConnectionInterface $connection) {}

    public function purgeOlderThan(DateTimeImmutable $cutoff, int $limit): int
    {
        return $this->connection->table('platform_audit_logs')
            ->where('occurred_at', '<', $cutoff->format('Y-m-d H:i:s.u'))
            ->limit($limit)
            ->delete();
    }

    public function append(AuditEntry $entry): void
    {
        $this->connection->table('platform_audit_logs')->insert([
            'actor_type' => $entry->actorType->value,
            'actor_id' => $entry->actorId,
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'metadata' => $entry->metadata === [] ? null : json_encode($entry->metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $entry->ipAddress,
            'user_agent' => $entry->userAgent === null ? null : mb_substr($entry->userAgent, 0, self::USER_AGENT_LENGTH),
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s.u'),
            'company_id' => $entry->companyId,
        ]);
    }
}
