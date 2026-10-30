<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\BlockedCompanyRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A job seeker blocks a company from finding them: at most 20, a company that is public (not missing, suspended or hidden). Blocking twice changes nothing.
 * The company is never told. The effects are in `CandidateReader`: the person is left out of that company's candidate search, recommendations, invitations by
 * slug and new chats without an application; an application still opens the applicant profile.
 */
final readonly class BlockCompany
{
    public const int LIMIT = 20;

    public function __construct(
        private JobSeekerGuard $guard,
        private BlockedCompanyRepository $blocked,
        private ProfileRepository $profiles,
        private PublicCompanies $companies,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the company is missing, suspended or hidden
     * @throws BusinessRuleViolation `blocked-companies-limit`
     */
    public function execute(Actor $actor, int $companyId): void
    {
        $userId = $this->guard->userId($actor);

        if ($this->companies->card($companyId) === null) {
            throw EntityNotFound::of('Company', $companyId);
        }

        $now = $this->clock->now();

        $this->transactions->run(function () use ($userId, $companyId, $now): void {
            // The profile row is locked first, so two simultaneous blocks cannot both pass the limit.
            $this->profiles->lock($userId, $now);

            if ($this->blocked->exists($userId, $companyId)) {
                return;
            }

            if ($this->blocked->count($userId) >= self::LIMIT) {
                throw new BusinessRuleViolation('You can block at most :max companies.', 'blocked-companies-limit', parameters: ['max' => self::LIMIT]);
            }

            $this->blocked->add($userId, $companyId, $now);
        });
    }
}
