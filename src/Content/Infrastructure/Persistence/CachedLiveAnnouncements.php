<?php

namespace Src\Content\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;
use Src\Content\Application\Announcements\LiveAnnouncements;
use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementAudience;
use Src\Content\Domain\AnnouncementLevel;
use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Application\Clock;

/** The live announcements of an audience, kept 60 seconds in the cache as plain arrays (a reader of `all` also sees none of the other audiences' banners). At most 20 are shown. */
final readonly class CachedLiveAnnouncements implements LiveAnnouncements
{
    public const int TTL_SECONDS = 60;

    private const int LIMIT = 20;

    private const string TIME_FORMAT = DATE_ATOM;

    public function __construct(
        private AnnouncementRepository $announcements,
        private Repository $cache,
        private Clock $clock,
    ) {}

    public function forAudience(AnnouncementAudience $audience): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember('content.announcements.live.'.$audience->value, self::TTL_SECONDS, function () use ($audience): array {
            $audiences = $audience === AnnouncementAudience::All ? [AnnouncementAudience::All] : [AnnouncementAudience::All, $audience];

            return array_map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'link_url' => $announcement->linkUrl,
                'audience' => $announcement->audience->value,
                'level' => $announcement->level->value,
                'starts_at' => $announcement->startsAt->format(self::TIME_FORMAT),
                'ends_at' => $announcement->endsAt->format(self::TIME_FORMAT),
            ], $this->announcements->live($audiences, $this->clock->now(), self::LIMIT));
        });

        return array_map(fn (array $row): Announcement => new Announcement(
            (int) $row['id'], (string) $row['title'], (string) $row['body'], $row['link_url'] === null ? null : (string) $row['link_url'],
            AnnouncementAudience::from((string) $row['audience']), AnnouncementLevel::from((string) $row['level']),
            new DateTimeImmutable((string) $row['starts_at']), new DateTimeImmutable((string) $row['ends_at']), null,
        ), $rows);
    }
}
