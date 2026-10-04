<?php

namespace Src\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Notification\Application\InApp\InAppNotificationRepository;

/** Works on the framework `notifications` table; every user query is scoped to the owner, so another user's id looks like a missing one. */
final readonly class DatabaseInAppNotificationRepository implements InAppNotificationRepository
{
    /** The short name Identity registers for public users in the polymorphic morph map. */
    private const string USER_MORPH_TYPE = 'user';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function markRead(int $userId, string $id, DateTimeImmutable $at): bool
    {
        $updated = $this->owned($userId)->where('id', $id)->whereNull('read_at')->update([
            'read_at' => $at->format(self::TIME_FORMAT),
            'updated_at' => $at->format(self::TIME_FORMAT),
        ]);

        return $updated > 0 || $this->owned($userId)->where('id', $id)->exists();
    }

    public function markAllRead(int $userId, DateTimeImmutable $at): int
    {
        return $this->owned($userId)->whereNull('read_at')->update([
            'read_at' => $at->format(self::TIME_FORMAT),
            'updated_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function delete(int $userId, string $id): bool
    {
        return $this->owned($userId)->where('id', $id)->delete() > 0;
    }

    public function purgeOlderThan(DateTimeImmutable $cutoff, int $limit): int
    {
        return $this->connection->table('notifications')
            ->where('created_at', '<', $cutoff->format(self::TIME_FORMAT))
            ->limit($limit)
            ->delete();
    }

    private function owned(int $userId): Builder
    {
        return $this->connection->table('notifications')
            ->where('notifiable_type', self::USER_MORPH_TYPE)
            ->where('notifiable_id', $userId);
    }
}
