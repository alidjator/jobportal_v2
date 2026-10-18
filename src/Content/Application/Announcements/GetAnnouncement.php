<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetAnnouncement
{
    public function __construct(private AnnouncementRepository $announcements) {}

    /** @throws EntityNotFound when the announcement does not exist */
    public function execute(int $id): Announcement
    {
        return $this->announcements->find($id) ?? throw EntityNotFound::of('Announcement', $id);
    }
}
