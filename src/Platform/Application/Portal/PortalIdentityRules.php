<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What an identity must satisfy before it goes live (`career-modes.md` section 11.4): the marketplace takes no scope and no company; a company site names its scope;
 * scope `company` takes exactly one live company; scope `group` takes live companies that are all tenants of the same group (a company without a group needs scope `company`).
 */
final readonly class PortalIdentityRules
{
    public function __construct(private PublicCompanies $companies, private GroupDirectory $groups) {}

    /**
     * @throws BusinessRuleViolation `portal-marketplace-takes-no-companies`, `portal-scope-required`, `portal-company-needs-one-company`, `portal-group-needs-companies`,
     *                               `portal-company-not-live` (extensions `company_ids`) or `portal-companies-not-in-one-group` (extensions `company_ids`)
     */
    public function check(PortalIdentity $identity): void
    {
        if ($identity->isMarketplace()) {
            if ($identity->scope !== null || $identity->companyIds !== []) {
                throw new BusinessRuleViolation('The marketplace takes no scope and no company.', 'portal-marketplace-takes-no-companies');
            }

            return;
        }

        if ($identity->mode !== PortalIdentity::COMPANY) {
            throw new BusinessRuleViolation('The mode must be marketplace or company.', 'portal-mode-unknown');
        }

        match ($identity->scope) {
            PortalIdentity::SCOPE_COMPANY => $this->checkOneCompany($identity),
            PortalIdentity::SCOPE_GROUP => $this->checkGroup($identity),
            default => throw new BusinessRuleViolation('A company site needs a scope: company or group.', 'portal-scope-required'),
        };
    }

    private function checkOneCompany(PortalIdentity $identity): void
    {
        if (count($identity->companyIds) !== 1) {
            throw new BusinessRuleViolation('A site of one company needs exactly one company.', 'portal-company-needs-one-company');
        }

        $this->requireLive($identity->companyIds);
    }

    private function checkGroup(PortalIdentity $identity): void
    {
        if ($identity->companyIds === []) {
            throw new BusinessRuleViolation('A site of a group needs at least one company.', 'portal-group-needs-companies');
        }

        $this->requireLive($identity->companyIds);

        $groups = array_map(fn (int $id): ?int => $this->groups->groupOfCompany($id), $identity->companyIds);

        if (in_array(null, $groups, true) || count(array_unique($groups)) !== 1) {
            $stray = array_values(array_filter($identity->companyIds, fn (int $id): bool => $this->groups->groupOfCompany($id) !== $groups[0] || $groups[0] === null));

            throw new BusinessRuleViolation(
                'The companies of a group site must all belong to the same group.', 'portal-companies-not-in-one-group',
                extensions: ['company_ids' => $stray],
            );
        }
    }

    /** @param  list<int>  $ids */
    private function requireLive(array $ids): void
    {
        $missing = array_values(array_diff($ids, array_keys($this->companies->cards($ids))));

        if ($missing !== []) {
            throw new BusinessRuleViolation(
                'These companies are missing, suspended or hidden: :ids.', 'portal-company-not-live',
                extensions: ['company_ids' => $missing], parameters: ['ids' => implode(', ', $missing)],
            );
        }
    }
}
