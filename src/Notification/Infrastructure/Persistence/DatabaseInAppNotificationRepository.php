<?php

namespace Src\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Notification\Application\InApp\InAppNotificationRepository;
use Src\Notification\Application\InApp\NotifiableKind;

/** Works on the framework `notifications` table; every query is scoped to the owner (a user or a platform admin, told apart by the morph name Identity registers), so another account's id looks like a missing one. */
final readonly class DatabaseInAppNotificationRepository implements InAppNotificationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function markRead(int $userId, string $id, DateTimeImmutable $at, NotifiableKind $kind = NotifiableKind::User): bool
    {
        $updated = $this->owned($userId, $kind)->where('id', $id)->whereNull('read_at')->update([
            'read_at' => $at->format(self::TIME_FORMAT),
            'updated_at' => $at->format(self::TIME_FORMAT),
        ]);

        return $updated > 0 || $this->owned($userId, $kind)->where('id', $id)->exists();
    }

    public function markAllRead(int $userId, DateTimeImmutable $at, NotifiableKind $kind = NotifiableKind::User): int
    {
        return $this->owned($userId, $kind)->whereNull('read_at')->update([
            'read_at' => $at->format(self::TIME_FORMAT),
            'updated_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function delete(int $userId, string $id, NotifiableKind $kind = NotifiableKind::User): bool
    {
        return $this->owned($userId, $kind)->where('id', $id)->delete() > 0;
    }

    public function purgeOlderThan(DateTimeImmutable $cutoff, int $limit): int
    {
        return $this->connection->table('notifications')
            ->where('created_at', '<', $cutoff->format(self::TIME_FORMAT))
            ->limit($limit)
            ->delete();
    }

    private function owned(int $userId, NotifiableKind $kind): Builder
    {
        return $this->connection->table('notifications')
            ->where('notifiable_type', $kind->value)
            ->where('notifiable_id', $userId);
    }
}
