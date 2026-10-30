<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ResumeImports;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What the person does with the CV the company sent: `use` copies it into their own resumes, `discard` refuses it. Either way the company's copy is deleted. Only the account that claimed
 * the invitation may choose, and only once.
 */
final readonly class ChooseEmailInvitationCv
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private ResumeImports $resumes,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return int the id of the new resume
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the invitation, or it holds no CV
     * @throws StateConflict `resume-limit-reached`
     * @throws BusinessRuleViolation `invalid-resume` or `file-too-large`
     */
    public function use(Actor $actor, string $token): int
    {
        $this->access->assertEnabled();
        $userId = $this->access->jobSeeker($actor);

        return $this->transactions->run(function () use ($token, $userId): int {
            $invitation = $this->claimed($token, $userId);
            $contents = $this->files->get((string) $invitation->cvPath) ?? throw EntityNotFound::of('Invitation', substr($token, 0, 4));
            $resumeId = $this->resumes->import($userId, (string) $invitation->cvName, $contents);

            $this->drop($invitation);

            return $resumeId;
        });
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function discard(Actor $actor, string $token): void
    {
        $this->access->assertEnabled();
        $userId = $this->access->jobSeeker($actor);

        $this->transactions->run(function () use ($token, $userId): void {
            $this->drop($this->claimed($token, $userId));
        });
    }

    /** @throws EntityNotFound */
    private function claimed(string $token, int $userId): EmailInvitation
    {
        $invitation = $this->invitations->findByTokenHash(InvitationToken::of($token)->hash(), forUpdate: true);

        if ($invitation === null || $invitation->claimedByUserId !== $userId || $invitation->cvPath === null) {
            throw EntityNotFound::of('Invitation', substr($token, 0, 4));
        }

        return $invitation;
    }

    private function drop(EmailInvitation $invitation): void
    {
        $this->invitations->save($invitation->withoutCv($this->clock->now()));
        $this->files->delete((string) $invitation->cvPath);
    }
}
