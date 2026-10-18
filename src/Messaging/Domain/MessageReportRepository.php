<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface MessageReportRepository
{
    public function hasOpenReport(int $conversationId, int $reporterUserId): bool;

    /** @param  list<array<string, mixed>>  $snapshot */
    public function add(int $conversationId, ?int $messageId, ReporterSide $side, int $reporterUserId, ReportReason $reason, ?string $note, array $snapshot, DateTimeImmutable $at): MessageReport;

    public function find(int $id, bool $forUpdate = false): ?MessageReport;

    /** @return Page<MessageReport> open ones oldest first, then the decided ones newest first; `$status` narrows to one status */
    public function page(?ReportStatus $status, PageRequest $request): Page;

    /** Closes an open report; the reporter is returned by the caller's earlier read. */
    public function decide(int $id, ReportStatus $status, int $adminId, ?string $note, DateTimeImmutable $at): void;

    /** Clears the snapshots of the reports decided before the cutoff; returns how many were cleared. */
    public function clearSnapshotsDecidedBefore(DateTimeImmutable $cutoff): int;

    /** For an erased account: forgets the user as a reporter (and the note), and clears the snapshots of the reports about the conversations the user took part in as the job seeker. */
    public function eraseUser(int $userId): void;
}
