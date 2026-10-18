<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** Every announcement for admins holding `announcements.manage`, newest start first. */
final readonly class ListAnnouncements
{
    public function __construct(private AnnouncementRepository $announcements) {}

    /** @return Page<Announcement> */
    public function execute(PageRequest $request): Page
    {
        return $this->announcements->page($request);
    }
}
