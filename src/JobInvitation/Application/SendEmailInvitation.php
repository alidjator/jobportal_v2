<?php

namespace Src\JobInvitation\Application;

use Psr\Log\LoggerInterface;
use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationChannel;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\RequestContext;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * A member invites a person by email to a live job and may attach the CV the company received (`docs/design/phase-8-manual-candidates.md` section 1). The answer is the same whether or
 * not the email belongs to an account, so the team cannot test addresses. The CV is stored first under a random name and the row added next; when that fails the file is deleted again.
 */
final readonly class SendEmailInvitation
{
    private const string DIRECTORY = 'jobinvitation/email-cvs';

    public function __construct(
        private EmailInvitationAccess $access,
        private JobDirectory $jobs,
        private EmailInvitationRepository $invitations,
        private InvitationQuota $quota,
        private EmailInvitationNotifier $notifier,
        private EmailSuppressions $suppressions,
        private FileStorage $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `feature-disabled`
     * @throws EntityNotFound the job (missing, foreign or not the member's)
     * @throws BusinessRuleViolation `job-not-live`, `email-suppressed`, `invalid-file` or `note-too-long`
     * @throws StateConflict `already-invited`
     * @throws TooManyAttempts `invitation-limit`
     */
    public function execute(
        Actor $actor, int $jobId, string $name, string $email, EmailInvitationChannel $channel, ?string $note, ?string $cvContents, ?string $cvName,
        ?string $language = null, ?string $ipAddress = null, ?string $userAgent = null,
    ): EmailInvitation {
        $member = $this->access->member($actor, CompanyPermission::ApplicationsManage, $jobId);

        if ($this->jobs->publicCompanyId($jobId) !== $member->companyId) {
            throw new BusinessRuleViolation('You can only invite people to a job that is live.', 'job-not-live');
        }

        if ($this->suppressions->reason($email) !== null) {
            throw new BusinessRuleViolation('This address does not receive email.', 'email-suppressed');
        }

        if ($cvContents !== null) {
            EmailInvitationCv::check($cvContents);
        }

        $token = InvitationToken::generate();
        $path = $cvContents === null ? null : self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.pdf';
        $now = $this->clock->now();
        $invitation = EmailInvitation::send(
            $member->companyId, $jobId, $name, $email, $channel, $note, $token->hash(), $path, $path === null ? null : EmailInvitationCv::cleanName((string) $cvName), $member->userId, $now,
        );

        if ($path !== null) {
            $this->files->put($path, (string) $cvContents);
        }

        try {
            $saved = $this->transactions->run(function () use ($invitation, $jobId, $email, $member, $actor, $ipAddress, $userAgent, $now): EmailInvitation {
                if ($this->invitations->openExists($jobId, $email)) {
                    throw new StateConflict('This person was already invited to the job.', 'already-invited');
                }

                $this->quota->assertRoom($member->companyId);
                $saved = $this->invitations->add($invitation);

                // No name or email in the metadata.
                $this->audit->record(new AuditEntry(
                    AuditActorType::User, $actor->id, AuditAction::INVITATION_EMAIL_SENT, 'email_invitation', $saved->id, ['job_id' => $jobId, 'channel' => $saved->channel->value, 'has_cv' => $saved->hasCv()],
                    $ipAddress, $userAgent, $now, $member->companyId,
                ));

                return $saved;
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        $this->notifier->send($saved, $token, $language ?? RequestContext::DEFAULT_LANGUAGE);

        return $saved;
    }

    private function deleteQuietly(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('The CV of an invitation by email could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
