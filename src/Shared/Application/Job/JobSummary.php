<?php

namespace Src\Shared\Application\Job;

/** What another context may know about a job it works with: whose it is, whether its content can still be changed and its level (a hint for suggestions). */
final readonly class JobSummary
{
    public function __construct(public int $id, public int $companyId, public bool $isEditable, public ?int $jobLevelId = null) {}
}
