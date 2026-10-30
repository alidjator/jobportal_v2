<?php

namespace Src\JobInvitation\Application;

use Psr\Log\LoggerInterface;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The team reads the CV attached to an open invitation; every read is audited (`invitation.cv_viewed`). After the claim, the refusal or the deletion there is no CV for the team. */
final readonly class GetEmailInvitationCv
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private FileStorage $files,
        private AuditTrail $audit,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `feature-disabled`
     * @throws EntityNotFound the invitation (missing, another company's or not the member's job) or its CV
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress = null, ?string $userAgent = null): EmailInvitationFile
    {
        $invitation = $this->invitations->find($id) ?? throw EntityNotFound::of('Invitation', $id);
        $member = $this->access->member($actor, CompanyPermission::ApplicationsView, $invitation->jobId);

        if ($invitation->companyId !== $member->companyId || ! $invitation->status->isOpen() || $invitation->cvPath === null) {
            throw EntityNotFound::of('Invitation', $id);
        }

        $contents = $this->files->get($invitation->cvPath);

        if ($contents === null) {
            $this->logger->error('The CV of an invitation by email is missing.', ['invitation_id' => $id, 'path' => $invitation->cvPath]);

            throw EntityNotFound::of('Invitation', $id);
        }

        $this->audit->record(new AuditEntry(
            AuditActorType::User, $actor->id, AuditAction::INVITATION_CV_VIEWED, 'email_invitation', $id, ['job_id' => $invitation->jobId], $ipAddress, $userAgent, $this->clock->now(), $member->companyId,
        ));

        return new EmailInvitationFile($contents, (string) $invitation->cvName);
    }
}
