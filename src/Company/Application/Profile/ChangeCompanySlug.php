<?php

namespace Src\Company\Application\Profile;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanySlug;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin changes the public URL name of the company. Old links stop working (no redirect) and there is no limit on how often it changes.
 * Setting the slug already in force changes nothing.
 */
final readonly class ChangeCompanySlug
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-slug` or `slug-reserved`
     * @throws StateConflict `slug-taken` when another company has the slug
     */
    public function execute(Actor $actor, string $slug): void
    {
        $company = $this->guard->company($actor);
        $new = CompanySlug::fromInput($slug)->value;

        if ($new === $company->slug()) {
            return;
        }

        if ($this->companies->slugExists($new, (int) $company->id)) {
            throw new StateConflict('This slug is already taken.', 'slug-taken');
        }

        $company->changeSlug($new);
        $this->companies->save($company);
    }
}
