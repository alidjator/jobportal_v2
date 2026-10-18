<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementAudience;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;

/** The banners the caller sees: anonymous visitors (and anyone who is neither a job seeker nor a company member) the ones for `all`; a job seeker or a company member also those for their own audience. */
final readonly class ListLiveAnnouncements
{
    public function __construct(private LiveAnnouncements $live) {}

    /** @return list<Announcement> */
    public function execute(?Actor $viewer): array
    {
        return $this->live->forAudience(match ($viewer?->kind) {
            ActorKind::JobSeeker => AnnouncementAudience::JobSeekers,
            ActorKind::CompanyMember => AnnouncementAudience::CompanyMembers,
            default => AnnouncementAudience::All,
        });
    }
}
