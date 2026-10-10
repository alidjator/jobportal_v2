<?php

namespace Src\Review\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A report of a published review, by a job seeker (`reporterId` is the account) or by the company (`reporterId` is the company). Only admins see it. */
final readonly class Report
{
    public function __construct(
        public int $reviewId,
        public ReporterKind $reporterKind,
        public int $reporterId,
        public ReportReason $reason,
        public ?string $note,
        public ReportStatus $status,
        public ?int $resolvedByAdminId,
        public ?DateTimeImmutable $resolvedAt,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}

    public static function open(int $reviewId, ReporterKind $kind, int $reporterId, ReportReason $reason, ?string $note, DateTimeImmutable $now): self
    {
        return new self($reviewId, $kind, $reporterId, $reason, $note, ReportStatus::Open, null, null, $now);
    }

    /** @throws StateConflict `report-not-open` */
    public function dismissed(int $adminId, DateTimeImmutable $now): self
    {
        if ($this->status !== ReportStatus::Open) {
            throw new StateConflict('This report was already handled.', 'report-not-open');
        }

        return new self($this->reviewId, $this->reporterKind, $this->reporterId, $this->reason, $this->note, ReportStatus::Dismissed, $adminId, $now, $this->createdAt, $this->id);
    }
}
