<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Domain\Events\GroupRoleChanged;
use Src\Organization\Domain\GroupMember;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Gives an account a group role, or replaces the permissions of the one it holds. The account must already be a member of a tenant of the group (the group role sits
 * on top of the company membership), and one account holds one group role. A group member never changes their own role.
 */
final readonly class SetGroupRole
{
    public function __construct(
        private GroupRoleAccess $access,
        private GroupMemberRepository $members,
        private LegalEntityRepository $entities,
        private CompanyMembershipResolver $memberships,
        private TenantRegistry $tenants,
        private GroupMemberViews $views,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<GroupPermission>  $permissions
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-group-permissions`, `cannot-change-own-group-role` or `user-not-in-group`
     * @throws StateConflict `user-in-another-group`
     */
    public function execute(Actor $actor, ?int $groupId, int $userId, array $permissions, ?string $ipAddress, ?string $userAgent): GroupMemberView
    {
        $groupId = $this->access->groupId($actor, $groupId);

        if ($permissions === []) {
            throw new BusinessRuleViolation('A group role needs at least one permission.', 'invalid-group-permissions');
        }

        if (! $actor->isAdmin() && $actor->id === $userId) {
            throw new BusinessRuleViolation('You cannot change your own group role.', 'cannot-change-own-group-role');
        }

        $this->assertBelongsToGroup($groupId, $userId);

        $existing = $this->members->findByUser($userId);

        if ($existing !== null && $existing->groupId !== $groupId) {
            throw new StateConflict('The account holds a group role in another group.', 'user-in-another-group');
        }

        $member = new GroupMember($existing?->id, $groupId, $userId, $this->inCatalogOrder($permissions));
        $this->members->save($member);

        $this->events->dispatch([new GroupRoleChanged(
            $groupId, $userId, $existing === null ? 'granted' : 'changed', array_map(static fn (GroupPermission $permission): string => $permission->value, $member->permissions),
            (int) $actor->id, $actor->isAdmin(), $ipAddress, $userAgent, $this->clock->now(),
        )]);

        return $this->views->of([$this->members->findByUser($userId) ?? $member])[0];
    }

    /** @throws BusinessRuleViolation `user-not-in-group` */
    private function assertBelongsToGroup(int $groupId, int $userId): void
    {
        $companyId = $this->memberships->forUser($userId)?->companyId;
        $entityId = $companyId === null ? null : $this->tenants->legalEntityOf($companyId);
        $entity = $entityId === null ? null : $this->entities->find($entityId);

        if ($entity === null || $entity->groupId !== $groupId) {
            throw new BusinessRuleViolation('The account is not a member of a tenant of this group.', 'user-not-in-group');
        }
    }

    /**
     * @param  list<GroupPermission>  $permissions
     * @return list<GroupPermission>
     */
    private function inCatalogOrder(array $permissions): array
    {
        return array_values(array_filter(GroupPermission::cases(), static fn (GroupPermission $permission): bool => in_array($permission, $permissions, true)));
    }
}
