<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\MasterSpec;
use Src\LegacyImport\Application\MasterWriter;
use Src\LegacyImport\Application\SlugMaker;

final readonly class DatabaseMasterWriter implements MasterWriter
{
    private const int SLUG_MAX = 150;

    public function __construct(private ConnectionInterface $connection) {}

    public function create(MasterSpec $spec, string $name, ?string $kind, string $createdAt): int
    {
        $row = [
            'name' => json_encode(['id' => $name, 'en' => $name], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'slug' => $this->freeSlug($spec->newTable, $name),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        if ($kind !== null) {
            $row['kind'] = $kind;
        }

        return (int) $this->connection->table($spec->newTable)->insertGetId($row);
    }

    private function freeSlug(string $table, string $name): string
    {
        $base = SlugMaker::from($name, self::SLUG_MAX - 6);
        $base = $base === '' ? 'item' : $base;
        $slug = $base;

        for ($number = 2; $this->connection->table($table)->where('slug', $slug)->exists(); $number++) {
            $slug = $base.'-'.$number;
        }

        return $slug;
    }
}
