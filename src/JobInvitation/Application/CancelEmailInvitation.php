<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The team withdraws an open invitation: the name, the email and the CV are deleted at once. */
final readonly class CancelEmailInvitation
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private FileStorage $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `feature-disabled`
     * @throws EntityNotFound
     * @throws StateConflict `invitation-closed`
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $invitation = $this->invitations->find($id) ?? throw EntityNotFound::of('Invitation', $id);
        $member = $this->access->member($actor, CompanyPermission::ApplicationsManage, $invitation->jobId);

        if ($invitation->companyId !== $member->companyId) {
            throw EntityNotFound::of('Invitation', $id);
        }

        $path = $this->transactions->run(function () use ($id, $actor, $member, $ipAddress, $userAgent): ?string {
            $locked = $this->invitations->find($id, forUpdate: true) ?? throw EntityNotFound::of('Invitation', $id);
            $now = $this->clock->now();

            $this->invitations->save($locked->cancelled($now));
            $this->audit->record(new AuditEntry(
                AuditActorType::User, $actor->id, AuditAction::INVITATION_EMAIL_CANCELLED, 'email_invitation', $id, ['job_id' => $locked->jobId], $ipAddress, $userAgent, $now, $member->companyId,
            ));

            return $locked->cvPath;
        });

        if ($path !== null) {
            $this->files->delete($path);
        }
    }
}
