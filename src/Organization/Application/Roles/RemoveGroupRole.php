<?php

namespace Src\Organization\Application\Roles;

use Src\Organization\Domain\Events\GroupRoleChanged;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Removes a group role; the account stays a member of its tenant. A group member never removes their own role. */
final readonly class RemoveGroupRole
{
    public function __construct(
        private GroupRoleAccess $access,
        private GroupMemberRepository $members,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound the account holds no role in this group
     * @throws BusinessRuleViolation `cannot-change-own-group-role`
     */
    public function execute(Actor $actor, ?int $groupId, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $groupId = $this->access->groupId($actor, $groupId);

        if (! $actor->isAdmin() && $actor->id === $userId) {
            throw new BusinessRuleViolation('You cannot change your own group role.', 'cannot-change-own-group-role');
        }

        $member = $this->members->findByUser($userId);

        if ($member === null || $member->groupId !== $groupId) {
            throw EntityNotFound::of('Group role', $userId);
        }

        $this->members->delete($userId);
        $this->events->dispatch([new GroupRoleChanged($groupId, $userId, 'removed', [], (int) $actor->id, $actor->isAdmin(), $ipAddress, $userAgent, $this->clock->now())]);
    }
}
