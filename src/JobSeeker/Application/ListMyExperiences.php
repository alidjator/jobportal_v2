<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

final readonly class ListMyExperiences
{
    public function __construct(private JobSeekerGuard $guard, private ExperienceRepository $experiences) {}

    /**
     * @return list<WorkExperience>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        return $this->experiences->forUser($this->guard->userId($actor));
    }
}
