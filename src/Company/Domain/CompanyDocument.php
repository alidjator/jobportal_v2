<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

/**
 * The document a company submitted for one document type (at most one per company and type). Submitting again replaces the file and puts
 * the document back to `pending`. The review (approve or reject) is written with the admin side of the verification.
 */
final readonly class CompanyDocument
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public int $documentTypeId,
        public DocumentStatus $status,
        public string $filePath,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public ?string $rejectionReason,
        public ?string $rejectionNote,
        public DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $reviewedAt,
        public ?int $reviewedByAdminId,
    ) {}

    public static function submit(int $companyId, int $documentTypeId, string $filePath, string $originalName, string $mimeType, int $sizeBytes, DateTimeImmutable $at): self
    {
        return new self(null, $companyId, $documentTypeId, DocumentStatus::Pending, $filePath, $originalName, $mimeType, $sizeBytes, null, null, $at, null, null);
    }

    /** A new file for the same type: back to `pending`, with the earlier decision and its reason cleared. */
    public function resubmit(string $filePath, string $originalName, string $mimeType, int $sizeBytes, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->companyId, $this->documentTypeId, DocumentStatus::Pending, $filePath, $originalName, $mimeType, $sizeBytes, null, null, $at, null, null);
    }

    /** An approved document is never deleted, only replaced by a new upload. */
    public function canBeDeleted(): bool
    {
        return $this->status !== DocumentStatus::Approved;
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }
}
