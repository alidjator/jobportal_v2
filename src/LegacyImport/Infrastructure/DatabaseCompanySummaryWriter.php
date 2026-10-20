<?php

namespace Src\LegacyImport\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\CompanySummaryWriter;

final readonly class DatabaseCompanySummaryWriter implements CompanySummaryWriter
{
    private const string TABLE = 'company_import_summaries';

    public function __construct(private ConnectionInterface $connection) {}

    public function save(int $companyId, array $items, DateTimeImmutable $at): void
    {
        $stored = $this->connection->table(self::TABLE)->where('company_id', $companyId)->value('items');
        $merged = [];

        foreach (is_string($stored) ? json_decode($stored, true, 512, JSON_THROW_ON_ERROR) : [] as $item) {
            $merged[$item['code']] = $item;
        }

        foreach ($items as $item) {
            $merged[$item['code']] = $item;
        }

        $json = json_encode(array_values($merged), JSON_THROW_ON_ERROR);

        if (is_string($stored)) {
            $this->connection->table(self::TABLE)->where('company_id', $companyId)->update(['items' => $json]);

            return;
        }

        $this->connection->table(self::TABLE)->insert([
            'company_id' => $companyId,
            'items' => $json,
            'created_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}
