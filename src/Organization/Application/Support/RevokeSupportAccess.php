<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Domain\Events\SupportAccessChanged;
use Src\Organization\Domain\SupportGrantRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin with `group.members.manage` ends a support grant of the group at once. */
final readonly class RevokeSupportAccess
{
    public function __construct(private GroupAccess $access, private SupportGrantRepository $grants, private EventDispatcher $events, private Clock $clock) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the grant is missing or belongs to another group
     * @throws StateConflict `support-grant-not-active`
     */
    public function execute(Actor $actor, int $grantId, ?string $ipAddress, ?string $userAgent): void
    {
        $context = $this->access->context($actor, GroupPermission::MembersManage);
        $grant = $this->grants->find($grantId);

        if ($grant === null || $grant->groupId !== $context->group->id) {
            throw EntityNotFound::of('Support grant', $grantId);
        }

        $now = $this->clock->now();
        $this->grants->save($grant->revoke((int) $actor->id, $now));

        $this->events->dispatch([new SupportAccessChanged(
            $grant->groupId, $grantId, $grant->adminId, 'revoked', $grant->expiresAt->format('Y-m-d H:i:s'), (int) $actor->id, $ipAddress, $userAgent, $now,
        )]);
    }
}
