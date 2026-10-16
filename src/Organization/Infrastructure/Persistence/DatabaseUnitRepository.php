<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Organization\Domain\ItemStatus;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\Unit;
use Src\Organization\Domain\UnitRepository;
use Src\Organization\Domain\UnitVersion;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseUnitRepository implements UnitRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id): ?Unit
    {
        $row = $this->connection->table('organization_units')->where('id', $id)->first();

        return $row === null ? null : $this->toUnit($row);
    }

    public function create(Unit $unit): Unit
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('organization_units')->insertGetId([
            'legal_entity_id' => $unit->legalEntityId, 'code' => $unit->code, 'status' => $unit->status->value, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException("Unit {$id} was just created.");
    }

    public function save(Unit $unit): void
    {
        $this->connection->table('organization_units')->where('id', $unit->id)->update(['status' => $unit->status->value, 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofEntity(int $legalEntityId): array
    {
        return $this->connection->table('organization_units')->where('legal_entity_id', $legalEntityId)->orderBy('code')->get()
            ->map(fn (stdClass $row): Unit => $this->toUnit($row))
            ->all();
    }

    public function codeTaken(int $legalEntityId, string $code): bool
    {
        return $this->connection->table('organization_units')->where('legal_entity_id', $legalEntityId)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists();
    }

    public function versionsOf(int $unitId): array
    {
        return $this->timelinesFor($this->connection->table('organization_unit_versions')->where('unit_id', $unitId))[$unitId] ?? [];
    }

    public function timelinesOfEntity(int $legalEntityId): array
    {
        return $this->timelinesFor(
            $this->connection->table('organization_unit_versions as version')
                ->join('organization_units as unit', 'unit.id', '=', 'version.unit_id')
                ->where('unit.legal_entity_id', $legalEntityId)
                ->select('version.*'),
        );
    }

    public function replaceVersions(int $unitId, array $versions): void
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $this->connection->transaction(function () use ($unitId, $versions, $now): void {
            $this->connection->table('organization_unit_versions')->where('unit_id', $unitId)->delete();
            $this->connection->table('organization_unit_versions')->insert(array_map(static fn (UnitVersion $version): array => [
                'unit_id' => $unitId,
                'valid_from' => $version->validFrom->format('Y-m-d'),
                'valid_to' => $version->validTo?->format('Y-m-d'),
                'name' => $version->name,
                'parent_unit_id' => $version->parentUnitId,
                'unit_type_id' => $version->unitTypeId,
                'cost_centre_id' => $version->costCentreId,
                'created_at' => $now,
            ], $versions));
        });
    }

    public function inUse(int $id, DateTimeImmutable $today): bool
    {
        $day = $today->format('Y-m-d');
        $current = static fn ($query, string $alias) => $query->where(fn ($inner) => $inner->whereNull("{$alias}.valid_to")->orWhere("{$alias}.valid_to", '>=', $day));

        $asParent = $this->connection->table('organization_unit_versions as version')
            ->join('organization_units as unit', 'unit.id', '=', 'version.unit_id')
            ->where('unit.status', ItemStatus::Active->value)
            ->where('version.parent_unit_id', $id)
            ->tap(fn ($query) => $current($query, 'version'))
            ->exists();

        return $asParent || $this->connection->table('organization_position_versions as version')
            ->join('organization_positions as position', 'position.id', '=', 'version.position_id')
            ->where('position.status', ItemStatus::Active->value)
            ->where('version.unit_id', $id)
            ->tap(fn ($query) => $current($query, 'version'))
            ->exists();
    }

    /**
     * @param  Builder  $query
     * @return array<int, list<UnitVersion>>
     */
    private function timelinesFor($query): array
    {
        $byUnit = [];

        foreach ($query->get() as $row) {
            $byUnit[(int) $row->unit_id][] = new UnitVersion(
                (int) $row->unit_id,
                new DateTimeImmutable((string) $row->valid_from),
                $row->valid_to === null ? null : new DateTimeImmutable((string) $row->valid_to),
                (string) $row->name,
                $row->parent_unit_id === null ? null : (int) $row->parent_unit_id,
                (int) $row->unit_type_id,
                $row->cost_centre_id === null ? null : (int) $row->cost_centre_id,
            );
        }

        return array_map(Timeline::sorted(...), $byUnit);
    }

    private function toUnit(stdClass $row): Unit
    {
        return new Unit((int) $row->id, (int) $row->legal_entity_id, (string) $row->code, ItemStatus::from((string) $row->status));
    }
}
