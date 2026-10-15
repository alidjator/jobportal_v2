<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/** One request of a job seeker for a copy of their personal data, and the archive it produced while it lasts. */
final readonly class DataExport
{
    /** A request that is still building after this long is taken as dead, so it neither blocks a new request nor stays on the list. */
    public const int STALE_AFTER_MINUTES = 60;

    /** At most one request in this many hours (a failed one does not count). */
    public const int REQUEST_INTERVAL_HOURS = 24;

    /** The archive is kept this many hours after it was made. */
    public const int KEEP_HOURS = 24;

    public function __construct(
        public int $id,
        public int $userId,
        public DataExportStatus $status,
        public ?string $path,
        public ?int $sizeBytes,
        public ?DateTimeImmutable $readyAt,
        public ?DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
    ) {}

    /** Whether a build is still expected to finish. */
    public function isRunning(DateTimeImmutable $now): bool
    {
        return $this->status === DataExportStatus::Building && $this->createdAt > $now->modify('-'.self::STALE_AFTER_MINUTES.' minutes');
    }

    /** Whether the archive can be downloaded now. */
    public function isDownloadable(DateTimeImmutable $now): bool
    {
        return $this->status === DataExportStatus::Ready && $this->path !== null && $this->expiresAt !== null && $this->expiresAt > $now;
    }
}
