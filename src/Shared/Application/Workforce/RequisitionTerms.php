<?php

namespace Src\Shared\Application\Workforce;

/** What a job takes from the requisition it is opened for: the title and the job role of the position (the job's own copies stay editable). */
final readonly class RequisitionTerms
{
    public function __construct(public string $positionTitle, public ?int $jobRoleId) {}
}
