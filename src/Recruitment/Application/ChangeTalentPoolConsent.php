<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\TalentPoolConsentRevoked;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The applicant gives or takes back the consent to be kept in the company's talent pool, from any stage of the application and at any time. Giving it twice keeps
 * the first moment; revoking one that is not given does nothing. A revocation tells the talent pool, which drops the entry that rested on it.
 */
final readonly class ChangeTalentPoolConsent
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function give(Actor $actor, int $id): void
    {
        $userId = $this->guard->userId($actor);

        $this->transactions->run(function () use ($userId, $id): void {
            $application = $this->applications->findOfApplicant($userId, $id, forUpdate: true) ?? throw EntityNotFound::of('Application', $id);

            if (! $application->hasTalentPoolConsent()) {
                $this->applications->changeTalentPoolConsent($id, $this->clock->now());
            }
        });
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function revoke(Actor $actor, int $id): void
    {
        $userId = $this->guard->userId($actor);

        $event = $this->transactions->run(function () use ($userId, $id): ?TalentPoolConsentRevoked {
            $application = $this->applications->findOfApplicant($userId, $id, forUpdate: true) ?? throw EntityNotFound::of('Application', $id);

            if (! $application->hasTalentPoolConsent()) {
                return null;
            }

            $this->applications->changeTalentPoolConsent($id, null);

            return new TalentPoolConsentRevoked($id, $application->companyId, $userId, $this->clock->now());
        });

        if ($event !== null) {
            $this->events->dispatch([$event]);
        }
    }
}
