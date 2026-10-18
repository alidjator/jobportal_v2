<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The rules every invitation follows, whoever the member picked (a candidate of the search or an entry of the talent pool): the job is live and the member sees it,
 * a candidate who already applied is not invited, one invitation per candidate and job, and the company's daily cap applies. The candidate is told at once.
 */
final readonly class InviteCandidate
{
    public function __construct(
        private JobDirectory $jobs,
        private ApplicantRelations $relations,
        private InvitationRepository $invitations,
        private InvitationQuota $quota,
        private InvitationNotifier $notifier,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws EntityNotFound the job (missing, foreign or not the member's)
     * @throws BusinessRuleViolation `job-not-live`
     */
    public function assertJob(InvitingMember $member, int $jobId): void
    {
        $this->jobs->visibleToMember($jobId, $member->companyId, $member->userId, $member->seesAllJobs) ?? throw EntityNotFound::of('Job', $jobId);

        if ($this->jobs->publicCompanyId($jobId) !== $member->companyId) {
            throw new BusinessRuleViolation('You can only invite candidates to a job that is live.', 'job-not-live');
        }
    }

    /**
     * Invites the job seeker to a job that `assertJob` accepted.
     *
     * @throws BusinessRuleViolation `already-applied` or `note-too-long`
     * @throws StateConflict `already-invited`
     * @throws TooManyAttempts `invitation-limit`
     */
    public function invite(InvitingMember $member, int $jobId, int $userId, ?string $note): Invitation
    {
        $invitation = Invitation::send($member->companyId, $jobId, $userId, $member->userId, $note, $this->clock->now());

        if ($this->relations->hasAppliedToJob($jobId, $userId)) {
            throw new BusinessRuleViolation('This candidate already applied to the job.', 'already-applied');
        }

        $saved = $this->transactions->run(function () use ($invitation, $jobId, $userId, $member): Invitation {
            if ($this->invitations->exists($jobId, $userId)) {
                throw new StateConflict('This candidate was already invited to the job.', 'already-invited');
            }

            $this->quota->assertRoom($member->companyId);

            return $this->invitations->add($invitation);
        });

        $this->notifier->invited($saved);

        return $saved;
    }
}
