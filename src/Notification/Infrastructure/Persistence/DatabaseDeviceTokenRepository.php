<?php

namespace Src\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Notification\Application\DeviceTokens\DevicePlatform;
use Src\Notification\Application\DeviceTokens\DeviceTokenRepository;
use Src\Shared\Infrastructure\Persistence\NullOrder;

final readonly class DatabaseDeviceTokenRepository implements DeviceTokenRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function assign(int $userId, string $token, DevicePlatform $platform, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('notification_device_tokens')->upsert(
            [['user_id' => $userId, 'token' => $token, 'platform' => $platform->value, 'last_used_at' => $time, 'created_at' => $time, 'updated_at' => $time]],
            ['token'],
            ['user_id', 'platform', 'last_used_at', 'updated_at'],
        );
    }

    public function trim(int $userId, int $keep): void
    {
        // A user holds only a handful of tokens, so the ids to keep are read first.
        $kept = $this->connection->table('notification_device_tokens')
            ->where('user_id', $userId)
            ->orderByRaw(NullOrder::sql('last_used_at', 'desc'))
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('id')
            ->all();

        $this->connection->table('notification_device_tokens')
            ->where('user_id', $userId)
            ->whereNotIn('id', $kept)
            ->delete();
    }

    public function delete(int $userId, string $token): void
    {
        $this->connection->table('notification_device_tokens')
            ->where('user_id', $userId)
            ->where('token', $token)
            ->delete();
    }
}
