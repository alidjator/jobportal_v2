<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Applicant\ApplicantProfileSource;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * What a job seeker would meet when applying to a live job, read without locks and without writing: the reasons the apply request would refuse with, in its order (`email-not-verified`,
 * `profile-incomplete`, `already-applied`, `application-limit-reached`, `applications-unavailable`). The screening answers are not judged here. The apply request still decides.
 */
final readonly class GetApplyState
{
    private const string TIME_ZONE = 'Asia/Jakarta';

    public function __construct(
        private ApplicantGuard $guard,
        private JobDirectory $jobs,
        private ApplicantContacts $contacts,
        private ApplicantProfileSource $applicants,
        private ApplicationRepository $applications,
        private StageRepository $stages,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the job is not live (a job on a report hold is not)
     */
    public function execute(Actor $actor, int $jobId): ApplyState
    {
        $userId = $this->guard->userId($actor);
        $companyId = $this->jobs->publicCompanyId($jobId) ?? throw EntityNotFound::of('Job', $jobId);
        $now = $this->clock->now();
        $reasons = [];

        if (! ($this->contacts->contact($userId)?->emailVerified ?? false)) {
            $reasons[] = ['code' => 'email-not-verified'];
        }

        $missing = $this->applicants->missingFor($userId, $this->jobs->applyRequirements($jobId));

        if ($missing !== []) {
            $reasons[] = ['code' => 'profile-incomplete', 'missing' => $missing];
        }

        $application = $this->applications->liveApplicationOf($userId, $jobId);
        $block = $application === null ? null : $this->alreadyApplied($userId, $jobId, $now);

        if ($block !== null) {
            $reasons[] = $block;
        }

        $zone = new DateTimeZone(self::TIME_ZONE);
        $dayStart = new DateTimeImmutable($now->setTimezone($zone)->format('Y-m-d'), $zone);
        $left = max(0, $this->settings->integer(RecruitmentSettings::MAX_APPLICATIONS_PER_DAY) - $this->applications->countSubmittedSince($userId, $dayStart));

        if ($left === 0) {
            $reasons[] = ['code' => 'application-limit-reached'];
        }

        if (! $this->takesApplications($companyId)) {
            $reasons[] = ['code' => 'applications-unavailable'];
        }

        return new ApplyState(
            $reasons,
            $application === null ? null : ['id' => (int) $application->id, 'status' => $application->stageType->value, 'applied_at' => $application->appliedAt],
            $left,
        );
    }

    /**
     * Null when the live application is a rejected one whose waiting period is over (the apply request releases it); otherwise the reason, with the day the period ends when one runs.
     *
     * @return array{code: string, reapply_on?: string}|null
     */
    private function alreadyApplied(int $userId, int $jobId, DateTimeImmutable $now): ?array
    {
        $days = $this->jobs->reapplyAfterDays($jobId);
        $rejected = $days === null ? null : $this->applications->rejectedApplicationOf($userId, $jobId);

        if ($days === null || $rejected === null) {
            return ['code' => 'already-applied'];
        }

        $on = ReapplyWindow::opensOn($rejected['rejected_at'], $days);

        return $now < $on ? ['code' => 'already-applied', 'reapply_on' => $on->format('Y-m-d')] : null;
    }

    private function takesApplications(int $companyId): bool
    {
        foreach ($this->stages->forCompany($companyId) as $stage) {
            if ($stage->isDefault && $stage->type === StageType::Applied && $stage->id !== null) {
                return true;
            }
        }

        return false;
    }
}
