<?php

namespace Src\Shared\Application\Applicant;

/** What Recruitment needs from the job seeker's profile to take an application. Provided by JobSeeker, so Recruitment never reads the profile tables. */
interface ApplicantProfileSource
{
    /** The profile data and the chosen resume of the job seeker. A resume id that is not the applicant's gives a submission without a resume. */
    public function forApplication(int $userId, int $resumeId): ApplicantSubmission;

    /** Locks the applicant until the transaction ends, so two applications of the same person (and their daily count) run one after the other. */
    public function lock(int $userId): void;
}
