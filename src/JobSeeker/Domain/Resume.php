<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/** A PDF resume of a job seeker. The file lives on the private disk under `$path`; the name is only what the owner sees and what the download is called. */
final readonly class Resume
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $path,
        public int $sizeBytes,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}
}
