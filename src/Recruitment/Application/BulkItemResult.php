<?php

namespace Src\Recruitment\Application;

/** What happened to one application of a bulk action: moved (no code), or skipped with the error code of the single action. */
final readonly class BulkItemResult
{
    public function __construct(public int $applicationId, public ?string $code = null) {}

    public function wasMoved(): bool
    {
        return $this->code === null;
    }
}
