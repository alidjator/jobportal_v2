<?php

namespace Src\Recruitment\Application;

/** What happened to one application of a bulk action: moved (no code), or skipped with the error code of the single action. A move that filled the job says so. */
final readonly class BulkItemResult
{
    public function __construct(public int $applicationId, public ?string $code = null, public bool $jobFilled = false) {}

    public function wasMoved(): bool
    {
        return $this->code === null;
    }
}
