<?php

namespace Src\JobSeeker\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check every job seeker action starts with: the caller is a signed-in job seeker. */
final readonly class JobSeekerGuard
{
    /**
     * @return int the id of the job seeker's account
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function userId(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has a profile.', 'job-seeker-only');
        }

        return $actor->id;
    }
}
