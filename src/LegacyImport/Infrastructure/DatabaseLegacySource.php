<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Src\LegacyImport\Application\LegacySource;

final class DatabaseLegacySource implements LegacySource
{
    private ?ConnectionInterface $connection = null;

    public function __construct(private readonly DatabaseManager $databases, private readonly Repository $config) {}

    public function chunk(string $table, int $size, callable $each): void
    {
        $this->connection()->table($table)->orderBy('id')->chunkById($size, function ($rows) use ($each): void {
            $each($rows->all());
        });
    }

    public function all(string $table, array $columns = ['*']): array
    {
        return $this->connection()->table($table)->get($columns)->all();
    }

    public function whereIn(string $table, string $column, array $values, array $columns = ['*']): array
    {
        if ($values === []) {
            return [];
        }

        return $this->connection()->table($table)->whereIn($column, $values)->get($columns)->all();
    }

    private function connection(): ConnectionInterface
    {
        if ($this->connection === null) {
            /** @var array<string, mixed> $legacy */
            $legacy = $this->config->get('database.connections.legacy', []);
            /** @var array<string, mixed> $application */
            $application = $this->config->get('database.connections.'.$this->config->get('database.default'), []);

            LegacyConnectionGuard::assertSafe($legacy, $application);

            $this->connection = $this->databases->connection('legacy');
        }

        return $this->connection;
    }
}
