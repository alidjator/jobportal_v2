<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;

/**
 * A report about a conversation, as stored. `snapshot` is the reported message and up to 10 earlier ones (null once cleared); the admin reads the snapshot, never the live thread.
 *
 * @phpstan-type SnapshotEntry array{message_id: int, side: string, sent_at: string, text: ?string, attachments: list<string>, deleted: bool, reported: bool}
 */
final readonly class MessageReport
{
    /** @param  list<array<string, mixed>>|null  $snapshot */
    public function __construct(
        public int $id,
        public int $conversationId,
        public ?int $messageId,
        public ReporterSide $reporterSide,
        public ?int $reporterUserId,
        public ReportReason $reason,
        public ?string $note,
        public ?array $snapshot,
        public ReportStatus $status,
        public ?int $decidedByAdminId,
        public ?DateTimeImmutable $decidedAt,
        public ?string $decisionNote,
        public DateTimeImmutable $createdAt,
    ) {}
}
