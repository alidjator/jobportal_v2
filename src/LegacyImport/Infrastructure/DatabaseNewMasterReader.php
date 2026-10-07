<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\NewMasterReader;

/** Reads the `reference_*` tables directly (the documented exception of this context). The names are JSON `{id, en}` columns. */
final readonly class DatabaseNewMasterReader implements NewMasterReader
{
    public function __construct(private ConnectionInterface $connection) {}

    public function rows(string $table): array
    {
        $rows = [];

        foreach ($this->connection->table($table)->get(['id', 'slug', 'name']) as $row) {
            $names = json_decode((string) $row->name, true);

            $rows[] = [
                'id' => (int) $row->id,
                'slug' => (string) $row->slug,
                'names' => is_array($names) ? array_values(array_filter($names, 'is_string')) : [],
            ];
        }

        return $rows;
    }

    public function idBySlug(string $table, string $slug): ?int
    {
        $id = $this->connection->table($table)->where('slug', $slug)->value('id');

        return $id === null ? null : (int) $id;
    }
}
