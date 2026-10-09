<?php

namespace Src\Shared\Application\Matching;

/** The match percentage of one job seeker for one job, for the statistics of an application. Provided by Matching. */
interface ApplicantMatchScores
{
    /** 0 to 100, or null when the job seeker has no complete profile, the job does not exist or too little is known about the job to say. */
    public function percent(int $userId, int $jobId): ?int;
}
