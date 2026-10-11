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
     * Generates a CV from the profile as it is now, to attach to an application. Null when the profile still lacks something that an application needs
     * (nothing is generated then). A generation counts against the job seeker's hourly limit. Slow, so call it outside a transaction.
     *
     * @throws BusinessRuleViolation `template-unavailable` or `invalid-cv`
     * @throws TooManyAttempts `cv-limit`
     */
    public function generateCv(int $userId, GeneratedCvSpec $cv): ?ApplicantResume;

    /** As `forApplication`, with the resume made by `generateCv`; a null resume gives a submission without a resume. The completeness is checked now. */
    public function forApplicationWithResume(int $userId, ?ApplicantResume $resume): ApplicantSubmission;

    /** Locks the applicant until the transaction ends, so two applications of the same person (and their daily count) run one after the other. */
    public function lock(int $userId): void;
}
