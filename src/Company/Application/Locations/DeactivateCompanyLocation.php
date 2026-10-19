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

/** Takes a location out of the pickers. What already points to it still shows it. The main location cannot be deactivated: the main mark moves first. Deactivating an inactive location does nothing. */
final readonly class DeactivateCompanyLocation
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
     * @throws StateConflict `location-is-main`
     */
    public function execute(Actor $actor, int $locationId, ?string $ipAddress, ?string $userAgent): CompanyLocationView
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        [$location, $changed] = $this->transactions->run(function () use ($companyId, $locationId): array {
            // The same lock as the main move: a location cannot become inactive while it is being made main.
            $this->companies->lock($companyId);
            $location = $this->locations->find($companyId, $locationId) ?? throw new EntityNotFound('Location not found.', 'location-not-found');

            if ($location->isMain) {
                throw new StateConflict('The main location cannot be deactivated: make another location the main one first.', 'location-is-main');
            }

            if (! $location->isActive()) {
                return [$location, false];
            }

            $location = $location->deactivate($this->clock->now());
            $this->locations->save($location);

            return [$location, true];
        });

        if ($changed) {
            $this->events->dispatch([new CompanyLocationChanged($companyId, $locationId, 'deactivated', [], (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
        }

        return $this->views->one($location);
    }
}
