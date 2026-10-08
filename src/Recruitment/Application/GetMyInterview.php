<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The active interview of one of the applicant's own applications, or null when it has none. Another person's application does not exist for the caller. */
final readonly class GetMyInterview
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private InterviewRepository $interviews,
        private InterviewViews $views,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): ?InterviewView
    {
        $this->applications->findOfApplicant($this->guard->userId($actor), $applicationId) ?? throw EntityNotFound::of('Application', $applicationId);
        $interview = $this->interviews->active($applicationId);

        return $interview === null ? null : $this->views->one($interview);
    }
}
