<?php

namespace Src\Company\Application\Profile;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyRenamed;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin changes some fields of the company profile. `$changes` holds only what changes, keyed by profile property name; a null clears an optional field. Only a changed
 * classification is checked against the masters (an item an admin deactivated later does not block unrelated edits). The name of a verified company is locked (409 `name-locked`);
 * it changes through a name change request (`RequestNameChange`). The address is not part of the profile: it is the main work location, edited through the location use cases.
 */
final readonly class UpdateCompanyProfile
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private ReferenceValues $references,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws StateConflict `name-locked` when a verified company changes its name
     * @throws BusinessRuleViolation `unknown-industry`, `unknown-organization-type` or `unknown-team-size`
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

        $renamed = isset($changes['name']) && $changes['name'] !== $company->name();

        $company->changeProfile($profile);
        $this->companies->save($company);

        if ($renamed) {
            $this->events->dispatch([new CompanyRenamed((int) $company->id, $this->clock->now())]);
        }
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
