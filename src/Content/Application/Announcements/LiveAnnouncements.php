<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementAudience;

/** The announcements live now for one kind of reader. Cached 60 seconds per audience by the implementation. */
interface LiveAnnouncements
{
    /** @return list<Announcement> */
    public function forAudience(AnnouncementAudience $audience): array;
}
