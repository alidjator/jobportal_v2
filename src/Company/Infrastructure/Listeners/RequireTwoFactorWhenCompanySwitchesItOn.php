<?php

namespace Src\Company\Infrastructure\Listeners;

use Src\Company\Application\Images\CompanySettings;
use Src\Company\Domain\CompanyMember;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Accounts\MemberTwoFactor;
use Src\Shared\Application\Settings\CompanySettingChanged;

/**
 * `company.require_two_factor` went from off to on for a company (by its company admin, or by a platform admin's override): every member without a confirmed
 * factor loses their sessions and is emailed that the next sign-in asks for enrolment. The company admins already were required. Not queued: the sessions
 * must end in the request that switched the setting on.
 */
final readonly class RequireTwoFactorWhenCompanySwitchesItOn
{
    public function __construct(
        private CompanyMemberRepository $members,
        private MemberTwoFactor $factors,
    ) {}

    public function handle(CompanySettingChanged $event): void
    {
        if ($event->key !== CompanySettings::REQUIRE_TWO_FACTOR || $event->before === true || $event->after !== true) {
            return;
        }

        $this->factors->requireFor(array_values(array_map(
            static fn (CompanyMember $member): int => $member->userId,
            array_filter($this->members->ofCompany($event->companyId), static fn (CompanyMember $member): bool => ! $member->isCompanyAdmin()),
        )));
    }
}
