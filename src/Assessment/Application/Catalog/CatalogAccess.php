<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Application\AssessmentSettings;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The checks of a company member reading the catalogue of tests they may send. */
final readonly class CatalogAccess
{
    public function __construct(private CompanyDirectory $companies, private Settings $settings) {}

    /**
     * The company of the member.
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function companyId(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return $actor->membership->companyId;
    }

    public function psychologicalEnabled(int $companyId): bool
    {
        return $this->settings->boolean(AssessmentSettings::PSYCHOLOGICAL_ENABLED, $companyId);
    }
}
