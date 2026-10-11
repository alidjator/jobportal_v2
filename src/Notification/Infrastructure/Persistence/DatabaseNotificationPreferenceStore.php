<?php

namespace Src\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Notification\Application\Preferences\NotificationPreferenceStore;

final readonly class DatabaseNotificationPreferenceStore implements NotificationPreferenceStore
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function stored(int $userId): array
    {
        $values = [];

        foreach ($this->connection->table('notification_preferences')->where('user_id', $userId)->get(['key', 'channel', 'enabled']) as $row) {
            $values["{$row->key}.{$row->channel}"] = (bool) $row->enabled;
        }

        return $values;
    }

    public function storedFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $values = [];

        foreach ($this->connection->table('notification_preferences')->whereIn('user_id', $userIds)->get(['user_id', 'key', 'channel', 'enabled']) as $row) {
            $values[(int) $row->user_id]["{$row->key}.{$row->channel}"] = (bool) $row->enabled;
        }

        return $values;
    }

    public function set(int $userId, string $key, string $channel, bool $enabled, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('notification_preferences')->upsert(
            [['user_id' => $userId, 'key' => $key, 'channel' => $channel, 'enabled' => $enabled, 'created_at' => $time, 'updated_at' => $time]],
            ['user_id', 'key', 'channel'],
            ['enabled', 'updated_at'],
        );
    }
}
