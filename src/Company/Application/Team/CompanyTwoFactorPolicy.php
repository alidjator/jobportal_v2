<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Images\CompanySettings;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Company\MemberTwoFactorPolicy;
use Src\Shared\Application\Settings\Settings;

/** Who must have a second factor: every company admin, and every member of a company that switched `company.require_two_factor` on. Any other account may do without. */
final readonly class CompanyTwoFactorPolicy implements MemberTwoFactorPolicy
{
    public function __construct(
        private CompanyMemberRepository $members,
        private Settings $settings,
    ) {}

    public function isRequired(int $userId): bool
    {
        $membership = $this->members->findByUserId($userId);

        if ($membership === null) {
            return false;
        }

        return $membership->isCompanyAdmin() || $this->settings->boolean(CompanySettings::REQUIRE_TWO_FACTOR, $membership->companyId);
    }
}
