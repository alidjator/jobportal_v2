<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\TalentPool\PoolCandidates;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * A member invites a candidate to a live job they may see (a company admin: every job of the company; a recruiter: the jobs assigned to them). The candidate is one the
 * member found (the slug of a findable candidate) or an entry of the company's talent pool whose basis is still available (`docs/design/phase-8-talent-pool.md`
 * section 2). The rules of `InviteCandidate` apply either way.
 */
final readonly class CreateInvitation
{
    public function __construct(
        private InvitationAccess $access,
        private InviteCandidate $invitations,
        private CandidateDirectory $candidates,
        private PoolCandidates $pool,
    ) {}

    /**
     * Exactly one of `$candidateSlug` and `$entryId` is given.
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the job (missing, foreign or not theirs), the candidate (missing or not findable) or the talent pool entry (missing or another company's)
     * @throws BusinessRuleViolation `job-not-live`, `already-applied`, `note-too-long` or `entry-unavailable`
     * @throws StateConflict `already-invited`
     * @throws TooManyAttempts `invitation-limit`
     */
    public function execute(Actor $actor, int $jobId, ?string $candidateSlug, ?int $entryId, ?string $note): Invitation
    {
        $member = $this->access->member($actor);
        $this->invitations->assertJob($member, $jobId);

        return $this->invitations->invite($member, $jobId, $this->userId($member->companyId, $candidateSlug, $entryId), $note);
    }

    /** @throws EntityNotFound|BusinessRuleViolation */
    private function userId(int $companyId, ?string $candidateSlug, ?int $entryId): int
    {
        if ($entryId === null) {
            return ($this->candidates->findable((string) $candidateSlug) ?? throw EntityNotFound::of('Candidate', (string) $candidateSlug))->userId;
        }

        $users = $this->pool->availableUserIds($companyId, [$entryId]);

        if (! array_key_exists($entryId, $users)) {
            throw EntityNotFound::of('Talent pool entry', $entryId);
        }

        return $users[$entryId] ?? throw new BusinessRuleViolation('This candidate cannot be invited right now.', 'entry-unavailable');
    }
}
