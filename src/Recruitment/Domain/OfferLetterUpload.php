<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** A company's own letter PDF, uploaded for an application and waiting for the offer that uses it (kept 24 hours at most). */
final readonly class OfferLetterUpload
{
    public const int VALID_HOURS = 24;

    public function __construct(
        public int $applicationId,
        public int $uploadedBy,
        public string $path,
        public int $sizeBytes,
        public string $sha256,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}

    public function expiresAt(): DateTimeImmutable
    {
        return $this->createdAt->modify('+'.self::VALID_HOURS.' hours');
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt() > $now;
    }
}
