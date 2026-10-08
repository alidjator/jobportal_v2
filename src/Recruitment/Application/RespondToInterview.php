<?php

namespace Src\Recruitment\Application;

use Closure;
use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What the job seeker does with the active interview of their own application: confirm it, decline it, or propose another start (at most twice). The
 * team (the interviewer and who scheduled it) is told.
 */
final readonly class RespondToInterview
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private InterviewRepository $interviews,
        private InterviewViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `interview-not-pending` or `proposal-pending`
     */
    public function confirm(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::Confirmed, static fn (Interview $interview): Interview => $interview->confirm());
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
     * @throws StateConflict `no-active-interview`, `interview-not-pending`, `proposal-pending` or `proposal-limit`
     * @throws BusinessRuleViolation `invalid-interview`
     */
    public function propose(Actor $actor, int $applicationId, DateTimeImmutable $startsAt): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::Proposed, fn (Interview $interview): Interview => $interview->propose($startsAt, $this->clock->now()));
    }

    /** @param  Closure(Interview): Interview  $change */
    private function apply(Actor $actor, int $applicationId, InterviewChange $announce, Closure $change): InterviewView
    {
        $userId = $this->guard->userId($actor);

        [$saved, $event] = $this->transactions->run(function () use ($userId, $applicationId, $announce, $change): array {
            $application = $this->applications->findOfApplicant($userId, $applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');
            $saved = $change($interview);
            $this->interviews->update($saved);

            return [$saved, InterviewChanged::of($announce, $application, $saved, $this->clock->now())];
        });

        $this->events->dispatch([$event]);

        return $this->views->one($saved);
    }
}
