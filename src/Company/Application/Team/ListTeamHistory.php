<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\ActivityEntry;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The change history of the company's team for a company admin, from the audit log: invitations, joining, renaming, role and permission changes,
 * suspension, removal, the owner and the allowed email domains. Newest first, read-only; entries go only with the log's own retention. No entry shows
 * an IP address or a user agent.
 */
final readonly class ListTeamHistory
{
    /** @var list<string> */
    public const array ACTIONS = [
        AuditAction::COMPANY_INVITATION_SENT, AuditAction::COMPANY_INVITATION_UPDATED, AuditAction::COMPANY_INVITATION_RESENT, AuditAction::COMPANY_INVITATION_REVOKED,
        AuditAction::COMPANY_MEMBER_JOINED, AuditAction::COMPANY_MEMBER_RENAMED, AuditAction::COMPANY_MEMBER_ROLE_CHANGED, AuditAction::COMPANY_MEMBER_PERMISSIONS_CHANGED,
        AuditAction::COMPANY_MEMBER_SUSPENDED, AuditAction::COMPANY_MEMBER_REACTIVATED, AuditAction::COMPANY_MEMBER_REMOVED, AuditAction::COMPANY_OWNER_TRANSFERRED,
        AuditAction::COMPANY_INVITATION_EMAIL_DOMAINS_CHANGED,
    ];

    public function __construct(
        private CompanyAdminGuard $guard,
        private MemberActivity $activity,
        private InvitationRepository $invitations,
        private CompanyMemberAccounts $accounts,
    ) {}

    /**
     * @return CursorPage<TeamHistoryRow>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-cursor`, or `unknown-team-action` for an action that is not a team action
     */
    public function execute(Actor $actor, ?string $action, ?int $memberUserId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage
    {
        $companyId = (int) $this->guard->company($actor)->id;

        if ($action !== null && ! in_array($action, self::ACTIONS, true)) {
            throw new BusinessRuleViolation('This is not an action of the team history.', 'unknown-team-action');
        }

        $page = $this->activity->ofCompany($companyId, $action === null ? self::ACTIONS : [$action], $memberUserId, $from, $to, $request);

        $userIds = [];

        foreach ($page->items as $entry) {
            if ($entry->actorType === AuditActorType::User && $entry->actorId !== null) {
                $userIds[] = $entry->actorId;
            }

            if ($this->memberOf($entry) !== null) {
                $userIds[] = $this->memberOf($entry);
            }
        }

        $accounts = $this->accounts->summaries(array_values(array_unique($userIds)));
        $rows = [];

        foreach ($page->items as $entry) {
            $memberId = $this->memberOf($entry);
            $account = $memberId === null ? null : ($accounts[$memberId] ?? null);
            $invitation = isset($entry->metadata['invitation_id']) ? $this->invitations->findOfCompany($companyId, (int) $entry->metadata['invitation_id']) : null;

            $rows[] = new TeamHistoryRow(
                $this->withoutNetwork($entry),
                $entry->actorType === AuditActorType::User && $entry->actorId !== null ? ($accounts[$entry->actorId] ?? null)?->name : null,
                $memberId,
                $account->name ?? $invitation?->name,
                $account->email ?? $invitation?->email,
            );
        }

        return new CursorPage($rows, $page->nextCursor, $page->perPage);
    }

    private function memberOf(ActivityEntry $entry): ?int
    {
        $id = $entry->metadata['user_id'] ?? null;

        return is_int($id) ? $id : null;
    }

    /** The history never shows where a change came from: those stay in the member's own activity. */
    private function withoutNetwork(ActivityEntry $entry): ActivityEntry
    {
        return new ActivityEntry($entry->id, $entry->actorType, $entry->actorId, $entry->action, $entry->subjectType, $entry->subjectId, $entry->metadata, null, null, $entry->occurredAt);
    }
}
