<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Application\OrganizationPermissions;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Accounts\AdminSummary;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The platform admins a group admin may give support access to: the active ones that hold `groups.support`. */
final readonly class ListSupportAdmins
{
    public function __construct(private GroupAccess $access, private AdminAccounts $admins) {}

    /**
     * @return list<AdminSummary>
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     */
    public function execute(Actor $actor): array
    {
        $this->access->context($actor, GroupPermission::MembersManage);

        return $this->admins->holders(OrganizationPermissions::GROUPS_SUPPORT);
    }
}
