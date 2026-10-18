<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `announcements.manage` changes an announcement (the creator stays). */
final readonly class UpdateAnnouncement
{
    public function __construct(private AnnouncementRepository $announcements, private AnnouncementAudit $audit) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the announcement does not exist
     * @throws BusinessRuleViolation `invalid-announcement`
     */
    public function execute(Actor $actor, int $id, AnnouncementInput $input, ?string $ipAddress, ?string $userAgent): Announcement
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can change an announcement.');
        }

        $current = $this->announcements->find($id) ?? throw EntityNotFound::of('Announcement', $id);

        $changed = new Announcement($id, $input->title, $input->body, $input->linkUrl, $input->audience, $input->level, $input->startsAt, $input->endsAt, $current->createdByAdminId);
        $changed->assertValidWindow();

        $saved = $this->announcements->save($changed);
        $this->audit->record($actor, AuditAction::ANNOUNCEMENT_UPDATED, $saved, $ipAddress, $userAgent);

        return $saved;
    }
}
