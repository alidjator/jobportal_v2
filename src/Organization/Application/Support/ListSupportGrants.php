<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Domain\SupportGrantRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The support grants of the group made in the last 30 days, newest first: who may look, until when, and the ones that ended. */
final readonly class ListSupportGrants
{
    private const string WINDOW = '-30 days';

    public function __construct(private GroupAccess $access, private SupportGrantRepository $grants, private SupportGrantViews $views, private Clock $clock) {}

    /**
     * @return list<SupportGrantView>
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     */
    public function execute(Actor $actor): array
    {
        $context = $this->access->context($actor, GroupPermission::MembersManage);

        return $this->views->of($this->grants->ofGroupSince((int) $context->group->id, $this->clock->now()->modify(self::WINDOW)));
    }
}
