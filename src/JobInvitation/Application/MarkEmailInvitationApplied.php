<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\TransactionManager;

/** An application was submitted: the claimed invitation of that person to that job, if there is one, learns the application, and the company's copy of the CV is dropped (the application holds its own). Running it again changes nothing. */
final readonly class MarkEmailInvitationApplied
{
    public function __construct(
        private ApplicantRelations $relations,
        private EmailInvitationRepository $invitations,
        private FileStorage $files,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function execute(int $applicationId, int $jobId): void
    {
        $userId = $this->relations->applicantsOfApplications($jobId, [$applicationId])[$applicationId] ?? null;

        if ($userId === null) {
            return;
        }

        $path = $this->transactions->run(function () use ($applicationId, $jobId, $userId): ?string {
            $invitation = $this->invitations->claimedFor($jobId, $userId);

            if ($invitation === null || $invitation->applicationId !== null) {
                return null;
            }

            $locked = $this->invitations->find((int) $invitation->id, forUpdate: true);

            if ($locked === null || $locked->applicationId !== null) {
                return null;
            }

            $this->invitations->save($locked->applied($applicationId, $this->clock->now())->withoutCv($this->clock->now()));

            return $locked->cvPath;
        });

        if ($path !== null) {
            $this->files->delete($path);
        }
    }
}
