<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Application\OrganizationPermissions;
use Src\Organization\Domain\Events\SupportAccessChanged;
use Src\Organization\Domain\SupportGrant;
use Src\Organization\Domain\SupportGrantRepository;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A group admin with `group.members.manage` lets one named platform admin read the group's structure and activity log for 1 to 72 hours (read only, revocable). A
 * platform admin holds no such access by default.
 */
final readonly class GrantSupportAccess
{
    public const int MAX_HOURS = 72;

    public function __construct(
        private GroupAccess $access,
        private SupportGrantRepository $grants,
        private AdminAccounts $admins,
        private SupportGrantViews $views,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws BusinessRuleViolation `invalid-support-admin` or `invalid-support-duration`
     * @throws StateConflict `support-grant-exists`
     */
    public function execute(Actor $actor, int $adminId, int $hours, ?string $ipAddress, ?string $userAgent): SupportGrantView
    {
        $context = $this->access->context($actor, GroupPermission::MembersManage);
        $groupId = (int) $context->group->id;
        $now = $this->clock->now();

        if ($hours < 1 || $hours > self::MAX_HOURS) {
            throw new BusinessRuleViolation('Support access lasts 1 to '.self::MAX_HOURS.' hours.', 'invalid-support-duration');
        }

        if (! $this->admins->holds($adminId, OrganizationPermissions::GROUPS_SUPPORT)) {
            throw new BusinessRuleViolation('This platform admin cannot receive support access.', 'invalid-support-admin');
        }

        if ($this->grants->activeFor($groupId, $adminId, $now) !== null) {
            throw new StateConflict('This platform admin already has support access: revoke it first to change its duration.', 'support-grant-exists');
        }

        $grant = $this->grants->create(new SupportGrant(null, $groupId, $adminId, (int) $actor->id, $now->modify("+{$hours} hours"), null, null, $now));

        $this->events->dispatch([new SupportAccessChanged(
            $groupId, (int) $grant->id, $adminId, 'granted', $grant->expiresAt->format('Y-m-d H:i:s'), (int) $actor->id, $ipAddress, $userAgent, $now,
        )]);

        return $this->views->of([$grant])[0];
    }
}
