<?php

namespace Src\Recruitment\Application;

use Closure;
use DateTimeImmutable;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\RecordingConsent;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What the job seeker does with the active interview of their own application: confirm it, decline it, propose another start (at most twice, also to move a
 * confirmed interview up to the company's cut-off before it), or take back a recording consent they gave. The team (the interviewer and who scheduled it) is told.
 */
final readonly class RespondToInterview
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private InterviewRepository $interviews,
        private InterviewViews $views,
        private RecordingRepository $recordings,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `interview-not-pending` or `proposal-pending`
     * @throws BusinessRuleViolation `recording-consent-required` or `invalid-phone`
     */
    public function confirm(Actor $actor, int $applicationId, ?RecordingConsent $consent, ?string $phone = null, ?string $fallbackPhone = null): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::Confirmed, fn (Interview $interview): Interview => $interview->confirm($consent, $this->clock->now(), $phone, $fallbackPhone));
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `interview-not-active`
     */
    public function decline(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::Declined, static fn (Interview $interview): Interview => $interview->decline());
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `interview-not-pending`, `proposal-pending`, `proposal-limit` or `reschedule-too-late`
     * @throws BusinessRuleViolation `invalid-interview`, `reason-required`, `recording-consent-required` or `invalid-phone`
     */
    public function propose(
        Actor $actor,
        int $applicationId,
        DateTimeImmutable $startsAt,
        ?RecordingConsent $consent,
        ?string $phone = null,
        ?string $fallbackPhone = null,
        ?string $reason = null,
    ): InterviewView {
        return $this->apply($actor, $applicationId, InterviewChange::Proposed, fn (Interview $interview, Application $application): Interview => $interview->propose(
            $startsAt, $consent, $this->clock->now(), $phone, $fallbackPhone, $reason,
            $this->settings->integer(RecruitmentSettings::INTERVIEW_RESCHEDULE_CUTOFF_HOURS, $application->companyId),
        ));
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `interview-not-active`, `no-recording-consent` or `recording-started`
     */
    public function withdrawRecordingConsent(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::RecordingWithdrawn, function (Interview $interview): Interview {
            $withdrawn = $interview->withdrawRecordingConsent($this->clock->now());

            // Once a recording exists the consent was used; the job seeker asks the company to delete it instead.
            if ($this->recordings->exists((int) $interview->id)) {
                throw new StateConflict('The interview was already recorded, so the consent can no longer be withdrawn. Ask the company to delete the recording.', 'recording-started');
            }

            return $withdrawn;
        });
    }

    /** @param  Closure(Interview, Application): Interview  $change */
    private function apply(Actor $actor, int $applicationId, InterviewChange $announce, Closure $change): InterviewView
    {
        $userId = $this->guard->userId($actor);

        [$saved, $event] = $this->transactions->run(function () use ($userId, $applicationId, $announce, $change): array {
            $application = $this->applications->findOfApplicant($userId, $applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');
            $saved = $change($interview, $application);
            $this->interviews->update($saved);

            return [$saved, InterviewChanged::of($announce, $application, $saved, $this->clock->now())];
        });

        $this->events->dispatch([$event]);

        return $this->views->one($saved);
    }
}
