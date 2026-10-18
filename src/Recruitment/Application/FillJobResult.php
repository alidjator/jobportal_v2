<?php

namespace Src\Recruitment\Application;

/** What "mark as filled" did: the job's status afterwards and what happened to each application asked to be rejected, in the order of the request. */
final readonly class FillJobResult
{
    /** @param  list<BulkItemResult>  $items  `wasMoved()` means rejected */
    public function __construct(public string $jobStatus, public array $items) {}
}
