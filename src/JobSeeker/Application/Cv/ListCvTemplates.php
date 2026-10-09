<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\CvTemplateState;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The CV templates a job seeker may choose from: the ones that are switched on. */
final readonly class ListCvTemplates
{
    public function __construct(private JobSeekerGuard $guard, private CvTemplates $templates) {}

    /**
     * @return list<CvTemplateState>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        $this->guard->userId($actor);

        return $this->templates->enabled();
    }
}
