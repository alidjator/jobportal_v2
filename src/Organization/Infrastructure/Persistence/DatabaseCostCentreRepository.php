<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\CostCentre;
use Src\Organization\Domain\CostCentreRepository;
use Src\Organization\Domain\ItemStatus;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseCostCentreRepository implements CostCentreRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id): ?CostCentre
    {
        $row = $this->connection->table('organization_cost_centres')->where('id', $id)->first();

        return $row === null ? null : $this->toCentre($row);
    }

    public function create(CostCentre $centre): CostCentre
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('organization_cost_centres')->insertGetId([
            ...$this->columns($centre), 'legal_entity_id' => $centre->legalEntityId, 'code' => $centre->code, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException("Cost centre {$id} was just created.");
    }

    public function save(CostCentre $centre): void
    {
        $this->connection->table('organization_cost_centres')->where('id', $centre->id)->update([...$this->columns($centre), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofEntity(int $legalEntityId): array
    {
        return $this->connection->table('organization_cost_centres')->where('legal_entity_id', $legalEntityId)->orderBy('code')->get()
            ->map(fn (stdClass $row): CostCentre => $this->toCentre($row))
            ->all();
    }

    public function codeTaken(int $legalEntityId, string $code): bool
    {
        return $this->connection->table('organization_cost_centres')->where('legal_entity_id', $legalEntityId)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists();
    }

    public function inUse(int $id, DateTimeImmutable $today): bool
    {
        return $this->connection->table('organization_unit_versions as version')
            ->join('organization_units as unit', 'unit.id', '=', 'version.unit_id')
            ->where('unit.status', ItemStatus::Active->value)
            ->where('version.cost_centre_id', $id)
            ->where(fn ($query) => $query->whereNull('version.valid_to')->orWhere('version.valid_to', '>=', $today->format('Y-m-d')))
            ->exists();
    }

    /** @return array<string, mixed> */
    private function columns(CostCentre $centre): array
    {
        return ['name' => $centre->name, 'status' => $centre->status->value];
    }

    private function toCentre(stdClass $row): CostCentre
    {
        return new CostCentre((int) $row->id, (int) $row->legal_entity_id, (string) $row->code, (string) $row->name, ItemStatus::from((string) $row->status));
    }
}
