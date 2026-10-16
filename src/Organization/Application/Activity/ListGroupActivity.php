<?php

namespace Src\Organization\Application\Activity;

use Src\Organization\Application\Group\GroupAccess;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\ActivityEntry;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The activity log of the actor's group for a member with `group.audit.view`: the group's own changes, what the members of its internal tenants did, and what the
 * platform did to the group, newest first, as far back as the audit log is kept. Unlike the company log it shows no IP address or user agent: the group sees what
 * happened, not where a member was.
 */
final readonly class ListGroupActivity
{
    public function __construct(
        private GroupAccess $access,
        private MemberActivity $activity,
        private CompanyMemberAccounts $accounts,
        private CompanyDirectory $companies,
    ) {}

    /**
     * @return CursorPage<GroupActivityRow>
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function execute(Actor $actor, GroupActivityFilter $filter, CursorRequest $request): CursorPage
    {
        $context = $this->access->context($actor, GroupPermission::AuditView);
        $page = $this->activity->ofGroupLog((int) $context->group->id, $filter->userId, $filter->companyId, $filter->action, $filter->from, $filter->to, $request);

        $userIds = array_values(array_unique(array_filter(array_map(
            static fn (ActivityEntry $e): ?int => $e->actorType === AuditActorType::User ? $e->actorId : null, $page->items,
        ))));
        $companyIds = array_values(array_unique(array_filter(array_map(static fn (ActivityEntry $e): ?int => $e->companyId, $page->items))));
        $names = $userIds === [] ? [] : $this->accounts->summaries($userIds);
        $companies = $companyIds === [] ? [] : $this->companies->summaries($companyIds);

        return new CursorPage(array_map(static fn (ActivityEntry $e): GroupActivityRow => new GroupActivityRow(
            $e,
            $e->actorType === AuditActorType::User && $e->actorId !== null ? ($names[$e->actorId] ?? null)?->name : null,
            $e->companyId === null ? null : ($companies[$e->companyId] ?? null)?->name,
        ), $page->items), $page->nextCursor, $page->perPage);
    }
}
