<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\ItemStatus;
use Src\Organization\Domain\UnitType;
use Src\Organization\Domain\UnitTypeRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseUnitTypeRepository implements UnitTypeRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id): ?UnitType
    {
        $row = $this->connection->table('organization_unit_types')->where('id', $id)->first();

        return $row === null ? null : $this->toType($row);
    }

    public function create(UnitType $type): UnitType
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('organization_unit_types')->insertGetId([...$this->columns($type), 'group_id' => $type->groupId, 'code' => $type->code, 'created_at' => $now, 'updated_at' => $now]);

        return $this->find($id) ?? throw new \LogicException("Unit type {$id} was just created.");
    }

    public function save(UnitType $type): void
    {
        $this->connection->table('organization_unit_types')->where('id', $type->id)->update([...$this->columns($type), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofGroup(int $groupId): array
    {
        return $this->connection->table('organization_unit_types')->where('group_id', $groupId)->orderBy('level_order')->orderBy('code')->get()
            ->map(fn (stdClass $row): UnitType => $this->toType($row))
            ->all();
    }

    public function codeTaken(int $groupId, string $code): bool
    {
        return $this->connection->table('organization_unit_types')->where('group_id', $groupId)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists();
    }

    public function inUse(int $id, DateTimeImmutable $today): bool
    {
        return $this->connection->table('organization_unit_versions as version')
            ->join('organization_units as unit', 'unit.id', '=', 'version.unit_id')
            ->where('unit.status', ItemStatus::Active->value)
            ->where('version.unit_type_id', $id)
            ->where(fn ($query) => $query->whereNull('version.valid_to')->orWhere('version.valid_to', '>=', $today->format('Y-m-d')))
            ->exists();
    }

    /** @return array<string, mixed> */
    private function columns(UnitType $type): array
    {
        return ['name' => $type->name, 'level_order' => $type->levelOrder, 'status' => $type->status->value];
    }

    private function toType(stdClass $row): UnitType
    {
        return new UnitType((int) $row->id, (int) $row->group_id, (string) $row->code, (string) $row->name, (int) $row->level_order, ItemStatus::from((string) $row->status));
    }
}
