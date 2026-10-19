<?php

namespace Src\Company\Application\Locations;

use Src\Company\Application\Images\CompanySettings;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyLocation;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyLocationChanged;
use Src\Company\Domain\LocationStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A member who may change the company profile adds a work location. It is never the main one (the main mark moves with `SetMainCompanyLocation`). The company may hold `company.max_locations`, inactive ones included. */
final readonly class CreateCompanyLocation
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyLocationRepository $locations,
        private CompanyLocationRules $rules,
        private CompanyLocationViews $views,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-location-name`, `location-name-taken`, `invalid-location`, `invalid-coordinates` or `location-limit-reached`
     */
    public function execute(Actor $actor, CompanyLocationInput $input, ?string $ipAddress, ?string $userAgent): CompanyLocationView
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;
        $address = $this->rules->address($input->address);
        $coordinates = $this->rules->coordinates($input->latitude, $input->longitude);

        $saved = $this->transactions->run(function () use ($companyId, $input, $address, $coordinates): CompanyLocation {
            // The company row is the lock that makes the limit and the name check hold for two requests at the same moment.
            $this->companies->lock($companyId);

            if ($this->locations->count($companyId) >= $this->settings->integer(CompanySettings::MAX_LOCATIONS, $companyId)) {
                throw new BusinessRuleViolation('The company has reached the largest number of locations.', 'location-limit-reached');
            }

            return $this->locations->add(new CompanyLocation(
                null, $companyId, $this->rules->name($companyId, $input->name), $input->type, false, LocationStatus::Active, $address, $this->rules->phone($input->phone), $coordinates,
            ));
        });

        $this->events->dispatch([new CompanyLocationChanged($companyId, (int) $saved->id, 'created', [], (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);

        return $this->views->one($saved);
    }
}
