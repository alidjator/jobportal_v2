<?php

namespace Src\Company\Application\Admin;

use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanySuspended;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin suspends a company, with a reason that is stored on the company (only admins read it) and audited. The company leaves the
 * public view and its members get `company-suspended` on company features; their tokens are not revoked and nothing is deleted.
 */
final readonly class SuspendCompany
{
    public function __construct(
        private CompanyRepository $companies,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company does not exist
     * @throws StateConflict `company-already-suspended`
     */
    public function execute(Actor $actor, int $companyId, string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can suspend a company.');
        }

        $now = $this->clock->now();

        // The row is locked, so two admins suspending or restoring at the same moment cannot both succeed.
        $this->transactions->run(function () use ($companyId, $reason, $now): void {
            $this->companies->lock($companyId);
            $company = $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);

            $company->suspend($reason, $now);
            $this->companies->save($company);
        });

        $this->events->dispatch([new CompanySuspended($companyId, (int) $actor->id, $reason, $ipAddress, $userAgent, $now)]);
    }
}
