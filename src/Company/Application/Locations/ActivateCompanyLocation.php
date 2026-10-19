<?php

namespace Src\Company\Application\Locations;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\Events\CompanyLocationChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Puts an inactive location back in the pickers. Activating an active location (the main one included) does nothing. */
final readonly class ActivateCompanyLocation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyLocationRepository $locations,
        private CompanyLocationViews $views,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound `location-not-found`
     */
    public function execute(Actor $actor, int $locationId, ?string $ipAddress, ?string $userAgent): CompanyLocationView
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;
        $location = $this->locations->find($companyId, $locationId) ?? throw new EntityNotFound('Location not found.', 'location-not-found');

        if ($location->isActive()) {
            return $this->views->one($location);
        }

        $location = $location->activate();
        $this->locations->save($location);
        $this->events->dispatch([new CompanyLocationChanged($companyId, $locationId, 'activated', [], (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);

        return $this->views->one($location);
    }
}
