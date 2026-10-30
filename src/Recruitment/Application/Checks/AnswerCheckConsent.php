<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Application\ApplicantGuard;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\CheckChange;
use Src\Recruitment\Domain\Events\CheckChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The candidate agrees to a check or refuses it. A refusal is shown to the team and nothing moves by itself. Only the candidate of the application may answer; another person's application does not exist for them. */
final readonly class AnswerCheckConsent
{
    public function __construct(
        private ApplicantGuard $guard,
        private ChecksAccess $access,
        private ApplicationRepository $applications,
        private ApplicationCheckRepository $checks,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the application, the check, or the feature while it is off
     * @throws StateConflict `check-closed` or `check-consent-not-required`
     */
    public function consent(Actor $actor, int $applicationId, int $checkId): ApplicationCheck
    {
        return $this->answer($actor, $applicationId, $checkId, fn (ApplicationCheck $check): ApplicationCheck => $check->consented($this->clock->now()), refused: false);
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `check-closed` or `check-consent-not-required`
     * @throws BusinessRuleViolation `invalid-check`
     */
    public function refuse(Actor $actor, int $applicationId, int $checkId, ?string $reason): ApplicationCheck
    {
        return $this->answer($actor, $applicationId, $checkId, fn (ApplicationCheck $check): ApplicationCheck => $check->refused($reason, $this->clock->now()), refused: true);
    }

    /**
     * @param  callable(ApplicationCheck): ApplicationCheck  $change
     * @param  bool  $refused  whether the answer is a refusal, which tells the member who requested the check
     */
    private function answer(Actor $actor, int $applicationId, int $checkId, callable $change, bool $refused): ApplicationCheck
    {
        $userId = $this->guard->userId($actor);

        if (! $this->access->enabled()) {
            throw EntityNotFound::of('Check', $checkId);
        }

        $result = $this->transactions->run(function () use ($userId, $applicationId, $checkId, $change, $refused): array {
            $application = $this->applications->find($applicationId, true);

            if ($application === null || $application->userId !== $userId) {
                throw EntityNotFound::of('Application', $applicationId);
            }

            $check = $this->checks->find($applicationId, $checkId, forUpdate: true) ?? throw EntityNotFound::of('Check', $checkId);

            if (! $check->consentRequired) {
                throw new StateConflict('This check does not need your consent.', 'check-consent-not-required');
            }

            $answered = $change($check);
            $this->checks->save($answered);

            return [$answered, $refused ? CheckChanged::of(CheckChange::ConsentRefused, $application, $answered, $this->clock->now()) : null];
        });

        if ($result[1] instanceof CheckChanged) {
            $this->events->dispatch([$result[1]]);
        }

        return $result[0];
    }
}
