<?php

namespace Src\Shared\Application\Applicant;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** What Recruitment needs from the job seeker's profile to take an application. Provided by JobSeeker, so Recruitment never reads the profile tables. */
interface ApplicantProfileSource
{
    /** The profile data and the chosen resume of the job seeker. A resume id that is not the applicant's gives a submission without a resume. */
    public function forApplication(int $userId, int $resumeId): ApplicantSubmission;

    /**
     * As `forApplication`, but the resume is a CV generated now from the profile instead of an uploaded file. It is only generated when the profile is complete
     * (otherwise the submission has no resume and lists what is missing). A generation counts against the job seeker's hourly limit.
     *
     * @throws BusinessRuleViolation `template-unavailable` or `invalid-cv`
     * @throws TooManyAttempts `cv-limit`
     */
    public function forApplicationWithGeneratedCv(int $userId, GeneratedCvSpec $cv): ApplicantSubmission;

    /** Locks the applicant until the transaction ends, so two applications of the same person (and their daily count) run one after the other. */
    public function lock(int $userId): void;
}
