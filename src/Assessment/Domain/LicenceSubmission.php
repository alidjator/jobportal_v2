<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A renewed licence a psychologist submitted with its document, waiting for the platform admin. Deciding it is final. */
final class LicenceSubmission
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $psychologistId,
        public readonly LicenceKind $kind,
        public readonly string $number,
        public readonly DateTimeImmutable $validUntil,
        public readonly string $documentPath,
        public readonly string $documentName,
        public readonly int $documentSize,
        private LicenceStatus $status,
        private ?int $decidedByAdminId,
        private ?DateTimeImmutable $decidedAt,
        private ?string $decisionNote,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function submit(
        int $psychologistId,
        LicenceKind $kind,
        string $number,
        DateTimeImmutable $validUntil,
        string $documentPath,
        string $documentName,
        int $documentSize,
    ): self {
        return new self(null, $psychologistId, $kind, $number, $validUntil, $documentPath, $documentName, $documentSize, LicenceStatus::Pending, null, null, null);
    }

    public function status(): LicenceStatus
    {
        return $this->status;
    }

    public function decidedByAdminId(): ?int
    {
        return $this->decidedByAdminId;
    }

    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function decisionNote(): ?string
    {
        return $this->decisionNote;
    }

    /** @throws StateConflict `licence-decided` */
    public function approve(int $adminId, DateTimeImmutable $now): void
    {
        $this->decide(LicenceStatus::Approved, $adminId, $now, null);
    }

    /** @throws StateConflict `licence-decided` */
    public function reject(int $adminId, DateTimeImmutable $now, string $note): void
    {
        $this->decide(LicenceStatus::Rejected, $adminId, $now, $note);
    }

    private function decide(LicenceStatus $status, int $adminId, DateTimeImmutable $now, ?string $note): void
    {
        if ($this->status !== LicenceStatus::Pending) {
            throw new StateConflict('This licence was decided already.', 'licence-decided');
        }

        $this->status = $status;
        $this->decidedByAdminId = $adminId;
        $this->decidedAt = $now;
        $this->decisionNote = $note;
    }
}
