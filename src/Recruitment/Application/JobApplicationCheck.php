<?php

namespace Src\Recruitment\Application;

/** Whether a job already has applications. Applications arrive in step 7; until then no job has any. */
interface JobApplicationCheck
{
    public function hasApplications(int $jobId): bool;
}
