<?php

namespace Src\Content\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Content\Domain\Announcement;
use Src\Content\Domain\AnnouncementAudience;
use Src\Content\Domain\AnnouncementLevel;
use Src\Content\Domain\AnnouncementRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

final readonly class DatabaseAnnouncementRepository implements AnnouncementRepository
{
    private const string TABLE = 'content_announcements';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $id): ?Announcement
    {
        $row = $this->connection->table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : $this->toAnnouncement($row);
    }

    public function save(Announcement $announcement): Announcement
    {
        $now = (new DateTimeImmutable)->format(self::TIME_FORMAT);
        $values = [
            'title' => $announcement->title,
            'body' => $announcement->body,
            'link_url' => $announcement->linkUrl,
            'audience' => $announcement->audience->value,
            'level' => $announcement->level->value,
            'starts_at' => $announcement->startsAt->format(self::TIME_FORMAT),
            'ends_at' => $announcement->endsAt->format(self::TIME_FORMAT),
            'updated_at' => $now,
        ];

        if ($announcement->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $announcement->id)->update($values);

            return $announcement;
        }

        $id = (int) $this->connection->table(self::TABLE)->insertGetId([...$values, 'created_by_admin_id' => $announcement->createdByAdminId, 'created_at' => $now]);

        return new Announcement(
            $id, $announcement->title, $announcement->body, $announcement->linkUrl, $announcement->audience, $announcement->level,
            $announcement->startsAt, $announcement->endsAt, $announcement->createdByAdminId,
        );
    }

    public function delete(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function page(PageRequest $request): Page
    {
        $total = $this->connection->table(self::TABLE)->count();
        $rows = $this->connection->table(self::TABLE)->orderByDesc('starts_at')->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page($rows->map(fn (object $row): Announcement => $this->toAnnouncement($row))->all(), $total, $request);
    }

    public function live(array $audiences, DateTimeImmutable $now, int $limit): array
    {
        $time = $now->format(self::TIME_FORMAT);

        return $this->connection->table(self::TABLE)
            ->whereIn('audience', array_map(fn (AnnouncementAudience $audience): string => $audience->value, $audiences))
            ->where('starts_at', '<=', $time)->where('ends_at', '>', $time)
            ->orderByRaw("CASE level WHEN 'important' THEN 0 ELSE 1 END")->orderByDesc('starts_at')->orderByDesc('id')
            ->limit($limit)->get()
            ->map(fn (object $row): Announcement => $this->toAnnouncement($row))->all();
    }

    private function toAnnouncement(object $row): Announcement
    {
        return new Announcement(
            (int) $row->id,
            (string) $row->title,
            (string) $row->body,
            $row->link_url === null ? null : (string) $row->link_url,
            AnnouncementAudience::from((string) $row->audience),
            AnnouncementLevel::from((string) $row->level),
            new DateTimeImmutable((string) $row->starts_at),
            new DateTimeImmutable((string) $row->ends_at),
            $row->created_by_admin_id === null ? null : (int) $row->created_by_admin_id,
        );
    }
}
