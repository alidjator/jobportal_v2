<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The active interview of one of the applicant's own applications, or null when it has none, with until when a confirmed one may be moved. Another person's
 * application does not exist for the caller.
 */
final readonly class GetMyInterview
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private InterviewRepository $interviews,
        private InterviewViews $views,
        private Settings $settings,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): ?InterviewView
    {
        $application = $this->applications->findOfApplicant($this->guard->userId($actor), $applicationId) ?? throw EntityNotFound::of('Application', $applicationId);
        $interview = $this->interviews->active($applicationId);

        return $interview === null ? null : $this->views->one($interview)
            ->withRescheduleUntil($interview->rescheduleUntil($this->settings->integer(RecruitmentSettings::INTERVIEW_RESCHEDULE_CUTOFF_HOURS, $application->companyId)));
    }
}
