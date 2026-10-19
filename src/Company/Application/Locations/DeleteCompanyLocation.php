<?php

namespace Src\Company\Application\Locations;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyLocationChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyLocationUsages;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Deletes a location that nothing refers to. A location that is referenced (an interview, a requisition, later a job or a placement) can only be deactivated; the foreign keys are the safety net behind the usage check. */
final readonly class DeleteCompanyLocation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyLocationRepository $locations,
        private CompanyLocationUsages $usages,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound `location-not-found`
     * @throws StateConflict `location-is-main` or `location-in-use`
     */
    public function execute(Actor $actor, int $locationId, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        $this->transactions->run(function () use ($companyId, $locationId): void {
            $this->companies->lock($companyId);
            $location = $this->locations->find($companyId, $locationId) ?? throw new EntityNotFound('Location not found.', 'location-not-found');

            if ($location->isMain) {
                throw new StateConflict('The main location cannot be deleted: make another location the main one first.', 'location-is-main');
            }

            if ($this->usages->isHeld($companyId, $locationId)) {
                throw new StateConflict('Something still points to this location: deactivate it instead.', 'location-in-use');
            }

            $this->locations->delete($companyId, $locationId);
        });

        $this->events->dispatch([new CompanyLocationChanged($companyId, $locationId, 'deleted', [], (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
