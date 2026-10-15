<?php

namespace Src\Company\Application\Team;

use Src\Company\Application\Images\CompanySettings;
use Src\Company\Domain\CompanyMember;
use Src\Shared\Application\Accounts\MemberTwoFactor;
use Src\Shared\Application\Settings\Settings;

/** The second factor of each member as the team page shows it: `on` (confirmed), `pending` (required, not enrolled yet) or `off`. */
final readonly class MemberTwoFactorStatuses
{
    public const string ON = 'on';

    public const string OFF = 'off';

    public const string PENDING = 'pending';

    public function __construct(
        private MemberTwoFactor $factors,
        private Settings $settings,
    ) {}

    /**
     * @param  list<CompanyMember>  $members
     * @return array<int, string> keyed by account id
     */
    public function of(int $companyId, array $members): array
    {
        $confirmed = $this->factors->confirmed(array_map(static fn (CompanyMember $member): int => $member->userId, $members));
        $companyRequires = $this->settings->boolean(CompanySettings::REQUIRE_TWO_FACTOR, $companyId);
        $statuses = [];

        foreach ($members as $member) {
            $statuses[$member->userId] = ($confirmed[$member->userId] ?? false)
                ? self::ON
                : ($member->isCompanyAdmin() || $companyRequires ? self::PENDING : self::OFF);
        }

        return $statuses;
    }
}
