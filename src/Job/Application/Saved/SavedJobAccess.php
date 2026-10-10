<?php

namespace Src\Job\Application\Saved;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check every saved-jobs action starts with: a signed-in job seeker. */
final readonly class SavedJobAccess
{
    /**
     * @return int the id of the job seeker's account
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function jobSeeker(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has saved jobs.', 'job-seeker-only');
        }

        return $actor->id;
    }
}
