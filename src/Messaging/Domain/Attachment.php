<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;

/** A file stored for messages: its random path on the private disk, the type read from its content, and the cleaned name its sender gave it. */
final readonly class Attachment
{
    public function __construct(
        public int $ownerUserId,
        public string $name,
        public string $path,
        public string $mime,
        public int $sizeBytes,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}
}
