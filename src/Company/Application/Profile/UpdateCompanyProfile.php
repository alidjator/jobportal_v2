<?php

namespace Src\Company\Application\Profile;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin changes some fields of the company profile. `$changes` holds only what changes, keyed by profile property name; a null
 * clears an optional field. Only a changed classification is checked against the masters (an item an admin deactivated later does not
 * block unrelated edits), and the location is always checked as one chain together with the part that is not changing. The name of a verified company
 * is locked (409 `name-locked`); it changes through a name change request (`RequestNameChange`).
 */
final readonly class UpdateCompanyProfile
{
    private const array LOCATION_FIELDS = ['provinceId', 'cityId', 'subdistrictId', 'villageId', 'postalCodeId'];

    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private ReferenceValues $references,
        private LocationReferences $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws StateConflict `name-locked` when a verified company changes its name
     * @throws BusinessRuleViolation `unknown-industry`, `unknown-organization-type`, `unknown-team-size` or `invalid-location`
     */
    public function execute(Actor $actor, array $changes): void
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);

        if (isset($changes['name']) && $changes['name'] !== $company->name() && $company->verifiedAt() !== null) {
            throw new StateConflict('The name of a verified company is locked: ask for a change with a document.', 'name-locked');
        }

        $this->assertActive($changes, 'industryId', 'industries', 'unknown-industry', 'The industry does not exist or is not active.');
        $this->assertActive($changes, 'organizationTypeId', 'organization-types', 'unknown-organization-type', 'The organization type does not exist or is not active.');
        $this->assertActive($changes, 'teamSizeId', 'team-sizes', 'unknown-team-size', 'The team size does not exist or is not active.');

        $profile = $company->profile()->with($changes);

        if (array_intersect(self::LOCATION_FIELDS, array_keys($changes)) !== []
            && ! $this->locations->isValidChain($profile->provinceId, $profile->cityId, $profile->subdistrictId, $profile->villageId, $profile->postalCodeId)) {
            throw new BusinessRuleViolation(
                'The location is not a matching chain: each level must belong to the one above it, and a level needs the levels above it.',
                'invalid-location',
            );
        }

        $company->changeProfile($profile);
        $this->companies->save($company);
    }

    /** @param  array<string, mixed>  $changes */
    private function assertActive(array $changes, string $field, string $master, string $code, string $message): void
    {
        $id = $changes[$field] ?? null;

        if ($id !== null && ! $this->references->isActive($master, (int) $id)) {
            throw new BusinessRuleViolation($message, $code);
        }
    }
}
