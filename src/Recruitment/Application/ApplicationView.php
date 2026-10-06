<?php

namespace Src\Recruitment\Application;

/** The applicant's detail of one application, with the title of the job and the name of its company (null when either no longer exists). */
final readonly class ApplicationView
{
    public function __construct(public ApplicationDetailData $data, public ?string $jobTitle, public ?string $companyName) {}
}
