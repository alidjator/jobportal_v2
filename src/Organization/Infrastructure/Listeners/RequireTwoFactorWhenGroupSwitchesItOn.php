<?php

namespace Src\Organization\Infrastructure\Listeners;

use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Accounts\MemberTwoFactor;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\GroupSettingKeys;
use Src\Shared\Application\Settings\GroupSettingChanged;

/**
 * `group.require_two_factor` went from off to on for a group: every member of its internal tenants without a confirmed factor loses their sessions and is emailed that the next
 * sign-in asks for enrolment (the rule of `phase-8-company-admin.md` section 1 "becoming required"; the company admins already were required). Not queued: the sessions must
 * end in the request that switched the setting on.
 */
final readonly class RequireTwoFactorWhenGroupSwitchesItOn
{
    public function __construct(
        private LegalEntityRepository $entities,
        private TenantRegistry $tenants,
        private CompanyTeam $team,
        private MemberTwoFactor $factors,
    ) {}

    public function handle(GroupSettingChanged $event): void
    {
        if ($event->key !== GroupSettingKeys::REQUIRE_TWO_FACTOR || $event->before === true || $event->after !== true) {
            return;
        }

        $entityIds = array_map(static fn ($entity): int => (int) $entity->id, $this->entities->ofGroup($event->groupId));
        $userIds = [];

        foreach ($this->tenants->tenantsOf($entityIds) as $companyId) {
            foreach ($this->team->roles($companyId) as $userId => $role) {
                if ($role !== 'company_admin') {
                    $userIds[] = $userId;
                }
            }
        }

        $this->factors->requireFor($userIds);
    }
}
