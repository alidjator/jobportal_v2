<?php

namespace Src\Shared\Application\Job;

/** Provided by Job: the facts of a job that the public search shows right now. */
interface LiveJobProfiles
{
    /** Null when the job is not searchable (not active, past its deadline, or of a hidden company), by the rule of the public job search. */
    public function of(int $jobId): ?LiveJobProfile;
}
