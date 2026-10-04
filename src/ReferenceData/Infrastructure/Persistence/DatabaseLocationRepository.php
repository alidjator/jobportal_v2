<?php

namespace Src\ReferenceData\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\ReferenceData\Application\Locations\LocationItem;
use Src\ReferenceData\Application\Locations\LocationLevel;
use Src\ReferenceData\Application\Locations\LocationRepository;
use stdClass;

/** Table and column names come only from `LocationCatalog` (code); the parent id is a bound parameter. */
final readonly class DatabaseLocationRepository implements LocationRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function list(LocationLevel $level, ?int $parentId): array
    {
        $columns = ['id', ...($level->hasBpsCode ? ['bps_code'] : []), ...($level->hasName ? ['name'] : []), ...$level->extraColumns];

        $builder = $this->connection->table($level->table)->select($columns);

        if ($level->parentColumn !== null && $parentId !== null) {
            $builder->where($level->parentColumn, $parentId);
        }

        $builder->orderBy($level->hasName ? 'name' : 'code')->orderBy('id');

        return array_map(fn (stdClass $row): LocationItem => $this->toItem($level, $row), $builder->get()->all());
    }

    private function toItem(LocationLevel $level, stdClass $row): LocationItem
    {
        $extras = [];

        foreach ($level->extraColumns as $column) {
            $extras[$column] = $row->{$column} === null ? null : (string) $row->{$column};
        }

        return new LocationItem(
            (int) $row->id,
            $level->hasBpsCode ? (string) $row->bps_code : null,
            $level->hasName ? (string) $row->name : null,
            $extras,
        );
    }
}
