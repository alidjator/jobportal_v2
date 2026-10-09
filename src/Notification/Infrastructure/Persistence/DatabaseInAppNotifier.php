<?php

namespace Src\Notification\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\InAppNotifier;

/** Inserts the rows of Laravel's standard `notifications` table for public users (morph name `user`, as Identity registers it). */
final readonly class DatabaseInAppNotifier implements InAppNotifier
{
    private const string USER_MORPH_TYPE = 'user';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function send(array $userIds, string $type, array $data = []): void
    {
        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            return;
        }

        $time = $this->clock->now()->format(self::TIME_FORMAT);
        // An empty `data` is written as an object, so clients always receive `{}` and never `[]`.
        $json = json_encode($data === [] ? (object) [] : $data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->connection->table('notifications')->insert(array_map(
            static fn (int $userId): array => [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => self::USER_MORPH_TYPE,
                'notifiable_id' => $userId,
                'data' => $json,
                'read_at' => null,
                'created_at' => $time,
                'updated_at' => $time,
            ],
            $userIds,
        ));
    }

    public function refresh(array $userIds, string $type, string $groupKey, array $data): void
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $group = (string) ($data[$groupKey] ?? '');
        $path = '$.'.$groupKey;
        $missing = [];

        foreach (array_values(array_unique($userIds)) as $userId) {
            $updated = $this->connection->table('notifications')
                ->where('notifiable_type', self::USER_MORPH_TYPE)
                ->where('notifiable_id', $userId)
                ->where('type', $type)
                ->whereNull('read_at')
                ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) = ?', [$path, $group])
                ->update(['data' => $json, 'created_at' => $time, 'updated_at' => $time]);

            if ($updated === 0) {
                $missing[] = $userId;
            }
        }

        $this->send($missing, $type, $data);
    }
}
