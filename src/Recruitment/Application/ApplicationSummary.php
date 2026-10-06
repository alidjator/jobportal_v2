<?php

namespace Src\Recruitment\Application;

/** One line of the applicant's list: the application with the title of the job and the name of its company (null when either no longer exists). */
final readonly class ApplicationSummary
{
    public function __construct(public ApplicationRow $row, public ?string $jobTitle, public ?string $companyName) {}
}
