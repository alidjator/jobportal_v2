<?php

namespace Src\Content\Application\Announcements;

use DateTimeImmutable;
use Src\Content\Domain\AnnouncementAudience;
use Src\Content\Domain\AnnouncementLevel;

final readonly class AnnouncementInput
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $linkUrl,
        public AnnouncementAudience $audience,
        public AnnouncementLevel $level,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}
}
