<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TalentPool\PoolCandidates;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * A member invites up to 50 talent pool entries to one live job (`docs/design/phase-8-talent-pool.md` section 2). The job and the note are checked once, for the whole
 * request. Each entry then goes through the rules of `InviteCandidate` on its own and takes a slot of the daily cap; one that cannot be invited is skipped with a reason
 * code and the others still go out. When the cap runs out, every entry that is left is skipped with `invitation-limit`.
 */
final readonly class BulkInviteFromPool
{
    public const int MAX_ENTRIES = 50;

    public function __construct(private InvitationAccess $access, private InviteCandidate $invitations, private PoolCandidates $pool) {}

    /**
     * @param  list<int>  $entryIds  1 to 50 distinct entries
     * @return list<InvitationOutcome> in the order of the entries
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the job (missing, foreign or not theirs)
     * @throws BusinessRuleViolation `job-not-live` or `note-too-long`
     */
    public function execute(Actor $actor, int $jobId, array $entryIds, ?string $note): array
    {
        $member = $this->access->member($actor);
        $this->invitations->assertJob($member, $jobId);
        Invitation::checkedNote($note);

        $users = $this->pool->availableUserIds($member->companyId, $entryIds);
        $outcomes = [];
        $capReached = false;

        foreach ($entryIds as $entryId) {
            $userId = $users[$entryId] ?? null;

            if ($userId === null) {
                $outcomes[] = InvitationOutcome::skipped($entryId, 'entry-unavailable');

                continue;
            }

            if ($capReached) {
                $outcomes[] = InvitationOutcome::skipped($entryId, 'invitation-limit');

                continue;
            }

            try {
                $outcomes[] = InvitationOutcome::invited($entryId, (int) $this->invitations->invite($member, $jobId, $userId, $note)->id);
            } catch (StateConflict|BusinessRuleViolation $refused) {
                // `already-invited` or `already-applied`; the note was checked before the loop.
                $outcomes[] = InvitationOutcome::skipped($entryId, $refused->errorCode());
            } catch (TooManyAttempts) {
                $capReached = true;
                $outcomes[] = InvitationOutcome::skipped($entryId, 'invitation-limit');
            }
        }

        return $outcomes;
    }
}
