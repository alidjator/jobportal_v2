<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\Events\CompanyMemberPermissionsChanged;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Company\Domain\MemberPermissionRules;
use Src\Company\Domain\MemberPreset;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin changes a member's role and permissions in one transaction, themselves included. A demotion without permissions gives the
 * Recruiter preset. Permissions apply to a member only, and a member needs at least one. The last company admin cannot be demoted. Giving a
 * member what they already have changes and audits nothing. The change takes effect at once: permissions are read on every request.
 */
final readonly class UpdateMember
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private CompanyMemberRepository $members,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  ?list<string>  $permissions  catalog names; null leaves the permissions as they are
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the actor's company
     * @throws BusinessRuleViolation `permissions-required` or `permission-requires-view`
     * @throws StateConflict `last-company-admin`, or `member-is-company-admin` when permissions are given for a company admin
     */
    public function execute(Actor $actor, int $userId, ?CompanyRole $role, ?array $permissions, ?string $ipAddress, ?string $userAgent): void
    {
        $company = $this->guard->company($actor);
        $companyId = (int) $company->id;

        $events = $this->transactions->run(function () use ($actor, $companyId, $userId, $role, $permissions, $ipAddress, $userAgent): array {
            $this->companies->lock($companyId);

            $member = $this->members->find($companyId, $userId) ?? throw EntityNotFound::of('Member', $userId);
            $newRole = $role ?? $member->role;
            $now = $this->clock->now();

            if ($newRole === CompanyRole::CompanyAdmin && $permissions !== null && $member->role === CompanyRole::CompanyAdmin) {
                throw new StateConflict('A company admin holds every permission.', 'member-is-company-admin');
            }

            if ($newRole !== $member->role && $member->role === CompanyRole::CompanyAdmin && count($this->members->lockCompanyAdminIds($companyId)) <= 1) {
                throw new StateConflict('A company needs at least one company admin.', 'last-company-admin');
            }

            if ($newRole === $member->role) {
                if ($newRole === CompanyRole::CompanyAdmin || $permissions === null) {
                    return [];
                }

                $after = MemberPermissionRules::validated($permissions);

                if ($this->sameSet($member->permissions, $after)) {
                    return [];
                }

                $this->members->replacePermissions($companyId, $userId, $after);

                return [new CompanyMemberPermissionsChanged($companyId, $userId, $member->permissions, $after, (int) $actor->id, $ipAddress, $userAgent, $now)];
            }

            $after = $newRole === CompanyRole::Member ? MemberPermissionRules::validated($permissions ?? MemberPreset::Recruiter->permissionNames()) : [];

            $this->members->changeRole($companyId, $userId, $newRole);
            $this->members->replacePermissions($companyId, $userId, $after);

            return [new CompanyMemberRoleChanged($companyId, $userId, $member->role->value, $newRole->value, $after, (int) $actor->id, $ipAddress, $userAgent, $now)];
        });

        $this->events->dispatch($events);
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function sameSet(array $before, array $after): bool
    {
        sort($before);
        sort($after);

        return $before === $after;
    }
}
