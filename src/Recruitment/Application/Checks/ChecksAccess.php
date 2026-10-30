<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Application\RecruitmentAccess;
use Src\Recruitment\Application\RecruitmentMember;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\TeamApplication;
use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The checks every action on pre-employment checks starts with: the feature is switched on (`recruitment.pre_employment_checks_enabled`), and the caller holds `checks.manage` to change anything (reading
 * the status of the checks of an application needs only the right to see the application). The candidate's side only needs the switch.
 */
final readonly class ChecksAccess
{
    public function __construct(private RecruitmentAccess $access, private TeamApplicationLookup $lookup, private Settings $settings) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     */
    public function member(Actor $actor): RecruitmentMember
    {
        $member = $this->access->permitted($actor, CompanyPermission::ChecksManage);
        $this->assertEnabled();

        return $member;
    }

    /**
     * @param  bool  $manage  whether the action needs `checks.manage`; a read needs only `applications.view`
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound
     */
    public function team(Actor $actor, int $applicationId, bool $manage, bool $forUpdate = false): TeamApplication
    {
        $team = $this->lookup->find($actor, $applicationId, $forUpdate, $manage ? CompanyPermission::ChecksManage : null);
        $this->assertEnabled();

        return $team;
    }

    /** @throws AccessDenied `feature-disabled` */
    public function assertEnabled(): void
    {
        if (! $this->settings->boolean(RecruitmentSettings::PRE_EMPLOYMENT_CHECKS_ENABLED)) {
            throw new AccessDenied('Pre-employment checks are not available yet.', 'feature-disabled');
        }
    }

    public function enabled(): bool
    {
        return $this->settings->boolean(RecruitmentSettings::PRE_EMPLOYMENT_CHECKS_ENABLED);
    }
}
