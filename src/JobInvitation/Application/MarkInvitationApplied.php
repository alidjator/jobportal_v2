<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\TransactionManager;

/** An application was submitted: the invitation of that candidate to that job, if there is one, becomes `applied`. Running it again changes nothing. */
final readonly class MarkInvitationApplied
{
    public function __construct(private ApplicantRelations $relations, private InvitationRepository $invitations, private Clock $clock, private TransactionManager $transactions) {}

    public function execute(int $applicationId, int $jobId): void
    {
        $userId = $this->relations->applicantsOfApplications($jobId, [$applicationId])[$applicationId] ?? null;

        if ($userId === null) {
            return;
        }

        $this->transactions->run(function () use ($applicationId, $jobId, $userId): void {
            $invitation = $this->invitations->findForJobAndUser($jobId, $userId, forUpdate: true);

            if ($invitation === null) {
                return;
            }

            $applied = $invitation->applied($applicationId, $this->clock->now());

            if ($applied !== $invitation) {
                $this->invitations->save($applied);
            }
        });
    }
}
