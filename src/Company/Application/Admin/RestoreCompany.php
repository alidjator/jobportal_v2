<?php

namespace Src\Company\Application\Admin;

use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyRestored;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A platform admin restores a suspended company: everything is as before, and the reason on the company is cleared. The optional note is only audited. */
final readonly class RestoreCompany
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
     * @throws StateConflict `company-not-suspended`
     */
    public function execute(Actor $actor, int $companyId, ?string $note, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can restore a company.');
        }

        $this->transactions->run(function () use ($companyId): void {
            $this->companies->lock($companyId);
            $company = $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);

            $company->restore();
            $this->companies->save($company);
        });

        $this->events->dispatch([new CompanyRestored($companyId, (int) $actor->id, $note, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
