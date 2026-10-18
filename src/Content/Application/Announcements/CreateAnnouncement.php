<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** An admin holding `announcements.manage` schedules an announcement. */
final readonly class CreateAnnouncement
{
    public function __construct(private AnnouncementRepository $announcements, private AnnouncementAudit $audit) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws BusinessRuleViolation `invalid-announcement`
     */
    public function execute(Actor $actor, AnnouncementInput $input, ?string $ipAddress, ?string $userAgent): Announcement
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can schedule an announcement.');
        }

        $draft = new Announcement(null, $input->title, $input->body, $input->linkUrl, $input->audience, $input->level, $input->startsAt, $input->endsAt, (int) $actor->id);
        $draft->assertValidWindow();

        $saved = $this->announcements->save($draft);
        $this->audit->record($actor, AuditAction::ANNOUNCEMENT_CREATED, $saved, $ipAddress, $userAgent);

        return $saved;
    }
}
