<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * A member invites a candidate they found to a live job they may see (a company admin: every job of the company; a recruiter: the jobs assigned to them). One
 * invitation per candidate and job; a candidate who already applied to the job is not invited; the company's daily cap applies. The candidate is told at once.
 */
final readonly class CreateInvitation
{
    public function __construct(
        private InvitationAccess $access,
        private JobDirectory $jobs,
        private CandidateDirectory $candidates,
        private ApplicantRelations $relations,
        private InvitationRepository $invitations,
        private InvitationQuota $quota,
        private InvitationNotifier $notifier,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the job (missing, foreign or not theirs) or the candidate (missing or not findable)
     * @throws BusinessRuleViolation `job-not-live`, `already-applied` or `note-too-long`
     * @throws StateConflict `already-invited`
     * @throws TooManyAttempts `invitation-limit`
     */
    public function execute(Actor $actor, int $jobId, string $candidateSlug, ?string $note): Invitation
    {
        $member = $this->access->member($actor);
        $this->jobs->visibleToMember($jobId, $member->companyId, $member->userId, $member->isCompanyAdmin) ?? throw EntityNotFound::of('Job', $jobId);

        if ($this->jobs->publicCompanyId($jobId) !== $member->companyId) {
            throw new BusinessRuleViolation('You can only invite candidates to a job that is live.', 'job-not-live');
        }

        $candidate = $this->candidates->findable($candidateSlug) ?? throw EntityNotFound::of('Candidate', $candidateSlug);
        $invitation = Invitation::send($member->companyId, $jobId, $candidate->userId, $member->userId, $note, $this->clock->now());

        if ($this->relations->hasAppliedToJob($jobId, $candidate->userId)) {
            throw new BusinessRuleViolation('This candidate already applied to the job.', 'already-applied');
        }

        $saved = $this->transactions->run(function () use ($invitation, $jobId, $candidate, $member): Invitation {
            if ($this->invitations->exists($jobId, $candidate->userId)) {
                throw new StateConflict('This candidate was already invited to the job.', 'already-invited');
            }

            $this->quota->assertRoom($member->companyId);

            return $this->invitations->add($invitation);
        });

        $this->notifier->invited($saved);

        return $saved;
    }
}
