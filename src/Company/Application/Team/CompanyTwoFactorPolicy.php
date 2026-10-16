<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Images\CompanySettings;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Company\MemberTwoFactorPolicy;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\Organization\GroupSettingKeys;
use Src\Shared\Application\Settings\Settings;

/**
 * Who must have a second factor: every company admin, every member of a company that switched `company.require_two_factor` on, and every member of an internal tenant
 * whose group requires it (`group.require_two_factor`, on by default; `erp-sso.md` section 3). Any other account may do without.
 */
final readonly class CompanyTwoFactorPolicy implements MemberTwoFactorPolicy
{
    public function __construct(
        private CompanyMemberRepository $members,
        private Settings $settings,
        private GroupDirectory $groups,
    ) {}

    public function isRequired(int $userId): bool
    {
        $membership = $this->members->findByUserId($userId);

        if ($membership === null) {
            return false;
        }

        return $membership->isCompanyAdmin()
            || $this->settings->boolean(CompanySettings::REQUIRE_TWO_FACTOR, $membership->companyId)
            || ($this->groups->groupOfCompany($membership->companyId) !== null && $this->settings->boolean(GroupSettingKeys::REQUIRE_TWO_FACTOR, $membership->companyId));
    }
}
