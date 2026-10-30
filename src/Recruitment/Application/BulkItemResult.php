<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/**
 * What happened to one application of a bulk action: moved (no code), waiting its undo window (`commitAt`, for a rejection), or skipped with the error code of the single action. A move that
 * filled the job says so.
 */
final readonly class BulkItemResult
{
    public function __construct(public int $applicationId, public ?string $code = null, public bool $jobFilled = false, public ?DateTimeImmutable $commitAt = null, public ?string $batchId = null) {}

    public function wasMoved(): bool
    {
        return $this->code === null && $this->commitAt === null;
    }

    /** A rejection that waits for the undo window to pass. */
    public function isPending(): bool
    {
        return $this->commitAt !== null;
    }
}
