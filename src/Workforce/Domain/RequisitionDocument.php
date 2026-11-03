<?php

namespace Src\Workforce\Domain;

use DateTimeImmutable;

/** A file attached to a requisition; `path` is where it sits on the private disk and is never shown to a reader. */
final readonly class RequisitionDocument
{
    public function __construct(
        public ?int $id,
        public int $requisitionId,
        public string $name,
        public string $path,
        public string $contentType,
        public int $sizeBytes,
        public int $uploadedById,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
