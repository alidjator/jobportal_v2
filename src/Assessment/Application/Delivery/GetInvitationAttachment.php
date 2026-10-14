<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The file of an invitation: for the team members who may see the application, and for the candidate it was sent to. Nobody else's: another candidate gets a 404. */
final readonly class GetInvitationAttachment
{
    public function __construct(
        private TeamApplications $applications,
        private InvitationRepository $invitations,
        private FileStorage $files,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function forTeam(Actor $actor, int $invitationId): AttachmentFile
    {
        $invitation = $this->invitations->find($invitationId) ?? throw EntityNotFound::of('Invitation', $invitationId);
        $this->applications->find($actor, $invitation->applicationId, permission: CompanyPermission::AssessmentsSend);

        return $this->file($invitation);
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function forCandidate(Actor $actor, int $invitationId): AttachmentFile
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        $invitation = $this->invitations->find($invitationId);

        if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $actor->id) {
            throw EntityNotFound::of('Invitation', $invitationId);
        }

        return $this->file($invitation);
    }

    private function file(Invitation $invitation): AttachmentFile
    {
        $attachment = $invitation->attachment ?? throw EntityNotFound::of('Attachment', (int) $invitation->id);
        $contents = $this->files->get($attachment->path) ?? throw EntityNotFound::of('Attachment', (int) $invitation->id);

        return new AttachmentFile($contents, $attachment->mimeType, $attachment->name);
    }
}
