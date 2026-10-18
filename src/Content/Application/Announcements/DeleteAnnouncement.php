<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `announcements.manage` removes an announcement (the banner disappears within a minute: the live list is cached that long). */
final readonly class DeleteAnnouncement
{
    public function __construct(private AnnouncementRepository $announcements, private AnnouncementAudit $audit) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the announcement does not exist
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can remove an announcement.');
        }

        $announcement = $this->announcements->find($id) ?? throw EntityNotFound::of('Announcement', $id);
        $this->announcements->delete($id);
        $this->audit->record($actor, AuditAction::ANNOUNCEMENT_DELETED, $announcement, $ipAddress, $userAgent);
    }
}
