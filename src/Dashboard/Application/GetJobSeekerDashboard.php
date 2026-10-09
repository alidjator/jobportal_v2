<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The dashboard of a job seeker, kept for 2 minutes. */
final readonly class GetJobSeekerDashboard
{
    private const int CACHE_SECONDS = 120;

    public function __construct(private DashboardComposer $composer) {}

    /** @throws AccessDenied `job-seeker-only` */
    public function execute(Actor $actor): DashboardView
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has this dashboard.', 'job-seeker-only');
        }

        return $this->composer->compose(new DashboardScope(DashboardAudience::JobSeeker, $actor->id), 'dashboard:job_seeker:'.$actor->id, self::CACHE_SECONDS);
    }
}
