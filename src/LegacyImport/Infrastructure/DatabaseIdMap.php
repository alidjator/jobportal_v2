<?php

namespace Src\LegacyImport\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Domain\IdMap;

final readonly class DatabaseIdMap implements IdMap
{
    private const string TABLE = 'legacyimport_id_map';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(string $entity, int $legacyId): ?int
    {
        $id = $this->connection->table(self::TABLE)->where('entity', $entity)->where('legacy_id', $legacyId)->value('new_id');

        return $id === null ? null : (int) $id;
    }

    public function findMany(string $entity, array $legacyIds): array
    {
        if ($legacyIds === []) {
            return [];
        }

        $found = [];

        foreach ($this->connection->table(self::TABLE)->where('entity', $entity)->whereIn('legacy_id', $legacyIds)->get(['legacy_id', 'new_id']) as $row) {
            $found[(int) $row->legacy_id] = (int) $row->new_id;
        }

        return $found;
    }

    public function remember(string $entity, int $legacyId, int $newId, DateTimeImmutable $at): void
    {
        $this->connection->table(self::TABLE)->insertOrIgnore([
            'entity' => $entity,
            'legacy_id' => $legacyId,
            'new_id' => $newId,
            'imported_at' => $this->utc($at),
        ]);
    }

    public function replace(string $entity, array $newIdsByLegacyId, DateTimeImmutable $at): void
    {
        $this->connection->table(self::TABLE)->where('entity', $entity)->delete();

        $time = $this->utc($at);
        $rows = [];

        foreach ($newIdsByLegacyId as $legacyId => $newId) {
            $rows[] = ['entity' => $entity, 'legacy_id' => $legacyId, 'new_id' => $newId, 'imported_at' => $time];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->connection->table(self::TABLE)->insert($chunk);
        }
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
