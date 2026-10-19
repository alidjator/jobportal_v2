<?php

namespace Src\Company\Application\Locations;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyLocationChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Moves the main mark to another active location of the company. Making the main location main again changes and audits nothing. */
final readonly class SetMainCompanyLocation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyLocationRepository $locations,
        private CompanyLocationViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound `location-not-found`
     * @throws StateConflict `location-inactive`
     */
    public function execute(Actor $actor, int $locationId, ?string $ipAddress, ?string $userAgent): CompanyLocationView
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        $moved = $this->transactions->run(function () use ($companyId, $locationId): bool {
            $this->companies->lock($companyId);
            $location = $this->locations->find($companyId, $locationId) ?? throw new EntityNotFound('Location not found.', 'location-not-found');

            if ($location->isMain) {
                return false;
            }

            if (! $location->isActive()) {
                throw new StateConflict('An inactive location cannot be the main location: activate it first.', 'location-inactive');
            }

            $this->locations->moveMain($companyId, $locationId);

            return true;
        });

        if ($moved) {
            $this->events->dispatch([new CompanyLocationChanged($companyId, $locationId, 'main_set', [], (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
        }

        return $this->views->one($this->locations->find($companyId, $locationId) ?? throw new EntityNotFound('Location not found.', 'location-not-found'));
    }
}
