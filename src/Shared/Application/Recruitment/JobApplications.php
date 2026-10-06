<?php

namespace Src\Shared\Application\Recruitment;

/** Lets other contexts ask whether a job has applications, without reading Recruitment's tables. Provided by Recruitment. */
interface JobApplications
{
    public function hasApplications(int $jobId): bool;
}
