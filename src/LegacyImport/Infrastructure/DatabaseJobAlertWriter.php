<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\JobAlertWriter;

final readonly class DatabaseJobAlertWriter implements JobAlertWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function insert(int $userId, string $name, array $criteria, bool $paused, string $now): int
    {
        return (int) $this->connection->table('jobalert_alerts')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'frequency' => 'daily',
            'criteria' => json_encode($criteria, JSON_THROW_ON_ERROR),
            'keywords' => '[]',
            'email' => true,
            'in_app' => true,
            'paused' => $paused,
            'last_run_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
