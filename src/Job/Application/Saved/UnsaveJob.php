<?php

namespace Src\Job\Application\Saved;

use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A job seeker removes a job from their saved jobs, whether or not the job is still live. Removing a job that is not saved changes nothing. */
final readonly class UnsaveJob
{
    public function __construct(private SavedJobAccess $access, private SavedJobRepository $saved) {}

    /** @throws AccessDenied `job-seeker-only` */
    public function execute(Actor $actor, int $jobId): void
    {
        $this->saved->remove($this->access->jobSeeker($actor), $jobId);
    }
}
