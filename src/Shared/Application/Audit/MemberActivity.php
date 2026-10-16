<?php

namespace Src\Shared\Application\Audit;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What the Company and Organization contexts read from the audit log, which belongs to Platform: the actions of one member, the team changes of one company and the log of a group. Newest
 * first, cursor paginated; the log's own retention bounds how far back it goes. Reading is not itself audited. Provided by Platform.
 */
interface MemberActivity
{
    /**
     * Every entry whose actor is the account.
     *
     * @return CursorPage<ActivityEntry>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function ofMember(int $userId, ?string $action, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage;

    /**
     * The entries of the given actions about the company (subject `company` with the company's id). `$memberUserId` keeps those that concern that member.
     *
     * @param  list<string>  $actions
     * @return CursorPage<ActivityEntry>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function ofCompany(int $companyId, array $actions, ?int $memberUserId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage;

    /**
     * The company activity log: every entry that belongs to the company (its members' actions and the platform admins' actions on it), newest first.
     * `$userId` keeps the actions of that member; `$actions` null keeps every action, a list keeps those (an empty list keeps none).
     *
     * @param  ?list<string>  $actions
     * @return CursorPage<ActivityEntry>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function ofCompanyLog(int $companyId, ?int $userId, ?array $actions, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage;

    /**
     * The group activity log: every entry that belongs to the group (its own actions, those of the members of its internal tenants and the platform admins' actions on
     * it), newest first. `$userId` keeps the actions of that member, `$companyId` those that belong to that tenant, `$action` one action.
     *
     * @return CursorPage<ActivityEntry>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function ofGroupLog(int $groupId, ?int $userId, ?int $companyId, ?string $action, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage;
}
