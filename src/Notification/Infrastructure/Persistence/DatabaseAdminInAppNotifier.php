<?php

namespace Src\Notification\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Src\Notification\Application\InApp\NotifiableKind;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\AdminInAppNotifier;

/** Inserts the rows of Laravel's standard `notifications` table for platform admins (morph name `admin`, as Identity registers it). */
final readonly class DatabaseAdminInAppNotifier implements AdminInAppNotifier
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock, private AdminAccounts $admins) {}

    public function sendToHolders(string $permission, string $type, array $data = []): void
    {
        $adminIds = array_map(static fn ($admin): int => $admin->id, $this->admins->holders($permission));

        if ($adminIds === []) {
            return;
        }

        $time = $this->clock->now()->format(self::TIME_FORMAT);
        // An empty `data` is written as an object, so clients always receive `{}` and never `[]`.
        $json = json_encode($data === [] ? (object) [] : $data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->connection->table('notifications')->insert(array_map(
            static fn (int $adminId): array => [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => NotifiableKind::Admin->value,
                'notifiable_id' => $adminId,
                'data' => $json,
                'read_at' => null,
                'created_at' => $time,
                'updated_at' => $time,
            ],
            $adminIds,
        ));
    }
}
