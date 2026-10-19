<?php

namespace Src\Company\Application\Locations;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\Events\CompanyLocationChanged;
use Src\Company\Domain\LocationType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Address;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A member who may change the company profile edits a work location. `$changes` holds only what changes, keyed by `name`, `type` (a LocationType), `phone`, `addressLine`,
 * `provinceId`, `cityId`, `subdistrictId`, `villageId`, `postalCodeId`, `latitude` and `longitude`; a null clears an optional field. The address is checked as one chain together with the
 * part that is not changing. An edit that changes nothing is not audited.
 */
final readonly class UpdateCompanyLocation
{
    private const array ADDRESS_FIELDS = ['addressLine', 'provinceId', 'cityId', 'subdistrictId', 'villageId', 'postalCodeId'];

    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyLocationRepository $locations,
        private CompanyLocationRules $rules,
        private CompanyLocationViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound `location-not-found`
     * @throws BusinessRuleViolation `invalid-location-name`, `location-name-taken`, `invalid-location` or `invalid-coordinates`
     */
    public function execute(Actor $actor, int $locationId, array $changes, ?string $ipAddress, ?string $userAgent): CompanyLocationView
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        [$updated, $fields] = $this->transactions->run(function () use ($companyId, $locationId, $changes): array {
            $current = $this->locations->find($companyId, $locationId) ?? throw new EntityNotFound('Location not found.', 'location-not-found');
            $values = [];

            if (array_key_exists('name', $changes)) {
                $values['name'] = $this->rules->name($companyId, (string) $changes['name'], $locationId);
            }

            if (array_key_exists('type', $changes) && $changes['type'] instanceof LocationType) {
                $values['type'] = $changes['type'];
            }

            if (array_key_exists('phone', $changes)) {
                $values['phone'] = $this->rules->phone($changes['phone'] === null ? null : (string) $changes['phone']);
            }

            if (array_intersect(self::ADDRESS_FIELDS, array_keys($changes)) !== []) {
                $values['address'] = $this->rules->address(new Address(
                    $this->pick($changes, 'addressLine', $current->address->line), $this->pick($changes, 'provinceId', $current->address->provinceId),
                    $this->pick($changes, 'cityId', $current->address->cityId), $this->pick($changes, 'subdistrictId', $current->address->subdistrictId),
                    $this->pick($changes, 'villageId', $current->address->villageId), $this->pick($changes, 'postalCodeId', $current->address->postalCodeId),
                ));
            }

            if (array_key_exists('latitude', $changes) || array_key_exists('longitude', $changes)) {
                $values['coordinates'] = $this->rules->coordinates(
                    $this->pick($changes, 'latitude', $current->coordinates?->latitude), $this->pick($changes, 'longitude', $current->coordinates?->longitude),
                );
            }

            $updated = $current->with($values);
            $before = get_object_vars($current);
            $after = get_object_vars($updated);
            $fields = array_values(array_filter(array_keys($values), static fn (string $field): bool => $after[$field] != $before[$field]));

            if ($fields !== []) {
                $this->locations->save($updated);
            }

            return [$updated, $fields];
        });

        if ($fields !== []) {
            $this->events->dispatch([new CompanyLocationChanged($companyId, $locationId, 'updated', $fields, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
        }

        return $this->views->one($updated);
    }

    /** @param  array<string, mixed>  $changes */
    private function pick(array $changes, string $key, mixed $current): mixed
    {
        return array_key_exists($key, $changes) ? $changes[$key] : $current;
    }
}
