<?php

namespace Src\LegacyImport\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\JobSeekerSummaryWriter;

final readonly class DatabaseJobSeekerSummaryWriter implements JobSeekerSummaryWriter
{
    private const string TABLE = 'jobseeker_import_summaries';

    public function __construct(private ConnectionInterface $connection) {}

    public function save(int $userId, array $items, DateTimeImmutable $at): void
    {
        $stored = $this->connection->table(self::TABLE)->where('user_id', $userId)->value('items');
        $merged = [];

        foreach (is_string($stored) ? json_decode($stored, true, 512, JSON_THROW_ON_ERROR) : [] as $item) {
            $merged[$item['code']] = $item;
        }

        foreach ($items as $item) {
            $merged[$item['code']] = $item;
        }

        $json = json_encode(array_values($merged), JSON_THROW_ON_ERROR);

        if (is_string($stored)) {
            $this->connection->table(self::TABLE)->where('user_id', $userId)->update(['items' => $json]);

            return;
        }

        $this->connection->table(self::TABLE)->insert([
            'user_id' => $userId,
            'items' => $json,
            'created_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}
