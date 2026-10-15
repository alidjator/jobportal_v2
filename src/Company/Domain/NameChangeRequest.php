<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A verified company asks to change its name, with a document that proves it (a decision of the ministry, a deed). An admin approves it (the name changes and the
 * badge stays) or rejects it with a note; the company may cancel it while it is pending. The document is deleted 90 days after the decision.
 */
final readonly class NameChangeRequest
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public int $requestedByUserId,
        public string $currentName,
        public string $newName,
        public string $reason,
        public ?string $documentPath,
        public ?string $documentName,
        public NameChangeStatus $status,
        public ?int $decidedByAdminId,
        public ?DateTimeImmutable $decidedAt,
        public ?string $decisionNote,
        public ?DateTimeImmutable $createdAt,
    ) {}

    public static function submit(int $companyId, int $requestedByUserId, string $currentName, string $newName, string $reason, string $documentPath, string $documentName): self
    {
        return new self(null, $companyId, $requestedByUserId, $currentName, $newName, $reason, $documentPath, $documentName, NameChangeStatus::Pending, null, null, null, null);
    }

    public function isPending(): bool
    {
        return $this->status === NameChangeStatus::Pending;
    }

    /** @throws StateConflict `name-change-not-pending` */
    public function approve(int $adminId, DateTimeImmutable $at): self
    {
        $this->assertPending();

        return $this->with(status: NameChangeStatus::Approved, decidedByAdminId: $adminId, decidedAt: $at);
    }

    /** @throws StateConflict `name-change-not-pending` */
    public function reject(int $adminId, string $note, DateTimeImmutable $at): self
    {
        $this->assertPending();

        return $this->with(status: NameChangeStatus::Rejected, decidedByAdminId: $adminId, decidedAt: $at, decisionNote: $note);
    }

    /** @throws StateConflict `name-change-not-pending` */
    public function cancel(DateTimeImmutable $at): self
    {
        $this->assertPending();

        return $this->with(status: NameChangeStatus::Cancelled, decidedAt: $at);
    }

    /** @throws StateConflict `name-change-not-pending` */
    private function assertPending(): void
    {
        if (! $this->isPending()) {
            throw new StateConflict('This name change request has already been decided.', 'name-change-not-pending');
        }
    }

    private function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
