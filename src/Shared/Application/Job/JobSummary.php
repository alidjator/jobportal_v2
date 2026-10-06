<?php

namespace Src\Shared\Application\Job;

/** What another context may know about a job it works with: whose it is and whether its content can still be changed. */
final readonly class JobSummary
{
    public function __construct(public int $id, public int $companyId, public bool $isEditable) {}
}
