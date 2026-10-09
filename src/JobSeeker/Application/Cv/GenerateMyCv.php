<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\GeneratedCvSpec;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The job seeker downloads a CV made from their own latest profile. */
final readonly class GenerateMyCv
{
    public function __construct(private JobSeekerGuard $guard, private GenerateCv $generate) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `template-unavailable` or `invalid-cv`
     * @throws TooManyAttempts `cv-limit`
     */
    public function execute(Actor $actor, GeneratedCvSpec $spec): GeneratedCv
    {
        return $this->generate->execute($this->guard->userId($actor), $spec);
    }
}
