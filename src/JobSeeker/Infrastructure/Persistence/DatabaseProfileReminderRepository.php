<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\ProfileReminderRepository;

final readonly class DatabaseProfileReminderRepository implements ProfileReminderRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function dueAmong(array $userIds, DateTimeImmutable $lastRemindedBefore): array
    {
        if ($userIds === []) {
            return [];
        }

        $blocked = $this->connection->table('jobseeker_profiles')
            ->whereIn('user_id', $userIds)
            ->where(fn ($query) => $query
                ->where('reminders_sent', '>=', self::MAX_REMINDERS)
                ->orWhere('last_reminded_at', '>=', $lastRemindedBefore->format(self::TIME_FORMAT)))
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_diff($userIds, $blocked));
    }

    public function markSent(int $userId, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('jobseeker_profiles')->insertOrIgnore(['user_id' => $userId, 'created_at' => $time, 'updated_at' => $time]);
        $this->connection->table('jobseeker_profiles')
            ->where('user_id', $userId)
            ->where('reminders_sent', '<', self::MAX_REMINDERS)
            ->increment('reminders_sent', 1, ['last_reminded_at' => $time]);
    }
}
