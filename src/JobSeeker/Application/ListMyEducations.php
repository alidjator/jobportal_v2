<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

final readonly class ListMyEducations
{
    public function __construct(private JobSeekerGuard $guard, private EducationRepository $educations) {}

    /**
     * @return list<Education>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        return $this->educations->forUser($this->guard->userId($actor));
    }
}
