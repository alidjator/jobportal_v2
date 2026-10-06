<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\Resume;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

final readonly class ListMyResumes
{
    public function __construct(private JobSeekerGuard $guard, private ResumeRepository $resumes) {}

    /**
     * @return list<Resume>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        return $this->resumes->forUser($this->guard->userId($actor));
    }
}
