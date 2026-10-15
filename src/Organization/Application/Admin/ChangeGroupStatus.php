<?php

namespace Src\Organization\Application\Admin;

use Src\Organization\Domain\Events\GroupRestored;
use Src\Organization\Domain\Events\GroupSuspended;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin suspends or restores a group. Suspending suspends every internal tenant of the group with the company suspension (and the group roles are
 * refused); restoring brings back only the tenants the group suspension suspended, so a tenant suspended on its own stays suspended. Nothing is deleted.
 */
final readonly class ChangeGroupStatus
{
    public function __construct(
        private GroupRepository $groups,
        private LegalEntityRepository $entities,
        private TenantRegistry $tenants,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `group-already-suspended` or `group-not-suspended`
     */
    public function suspend(Actor $actor, int $groupId, string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        AdminGuard::assert($actor, 'suspend a group');
        $now = $this->clock->now();

        $count = $this->transactions->run(function () use ($groupId, $reason, $now): int {
            $this->groups->lock($groupId);
            $group = $this->groups->find($groupId) ?? throw EntityNotFound::of('Group', $groupId);

            $this->groups->save($group->suspend($reason, $now));
            $tenants = $this->tenantIds($groupId);
            $this->tenants->suspendForGroup($tenants, $reason, $now);

            return count($tenants);
        });

        $this->events->dispatch([new GroupSuspended($groupId, $reason, $count, (int) $actor->id, true, $ipAddress, $userAgent, $now)]);
    }

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `group-not-suspended`
     */
    public function restore(Actor $actor, int $groupId, ?string $ipAddress, ?string $userAgent): void
    {
        AdminGuard::assert($actor, 'restore a group');

        $count = $this->transactions->run(function () use ($groupId): int {
            $this->groups->lock($groupId);
            $group = $this->groups->find($groupId) ?? throw EntityNotFound::of('Group', $groupId);

            $this->groups->save($group->restore());
            $tenants = $this->tenantIds($groupId);
            $this->tenants->restoreFromGroup($tenants);

            return count($tenants);
        });

        $this->events->dispatch([new GroupRestored($groupId, $count, (int) $actor->id, true, $ipAddress, $userAgent, $this->clock->now())]);
    }

    /** @return list<int> the tenants of every legal entity of the group */
    private function tenantIds(int $groupId): array
    {
        $entityIds = array_map(static fn (LegalEntity $entity): int => (int) $entity->id, $this->entities->ofGroup($groupId));

        return array_values($this->tenants->tenantsOf($entityIds));
    }
}
