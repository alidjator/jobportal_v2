<?php

namespace Src\Organization\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Organization\Domain\LegalEntityStatus;
use Src\Organization\Domain\LegalForm;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseLegalEntityRepository implements LegalEntityRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id): ?LegalEntity
    {
        $row = $this->connection->table('organization_legal_entities')->where('id', $id)->first();

        return $row === null ? null : $this->toEntity($row);
    }

    public function create(LegalEntity $entity): LegalEntity
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $id = (int) $this->connection->table('organization_legal_entities')->insertGetId([...$this->columns($entity), 'group_id' => $entity->groupId, 'created_at' => $now, 'updated_at' => $now]);

        return $this->find($id) ?? throw new \LogicException("Legal entity {$id} was just created.");
    }

    public function save(LegalEntity $entity): void
    {
        $this->connection->table('organization_legal_entities')->where('id', $entity->id)->update([...$this->columns($entity), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofGroup(int $groupId): array
    {
        return $this->connection->table('organization_legal_entities')->where('group_id', $groupId)->orderBy('legal_name')->orderBy('id')->get()
            ->map(fn (stdClass $row): LegalEntity => $this->toEntity($row))
            ->all();
    }

    public function nameTaken(int $groupId, string $name, ?int $exceptId = null): bool
    {
        return $this->connection->table('organization_legal_entities')
            ->where('group_id', $groupId)
            ->whereRaw('lower(legal_name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function countByGroup(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table('organization_legal_entities')->whereIn('group_id', $groupIds)->groupBy('group_id')->selectRaw('group_id, COUNT(*) as total')->get() as $row) {
            $counts[(int) $row->group_id] = (int) $row->total;
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function columns(LegalEntity $entity): array
    {
        return [
            'legal_name' => $entity->legalName,
            'legal_form' => $entity->legalForm->value,
            'npwp' => $entity->npwp,
            'nib' => $entity->nib,
            'address_line' => $entity->addressLine,
            'province_id' => $entity->provinceId,
            'city_id' => $entity->cityId,
            'subdistrict_id' => $entity->subdistrictId,
            'village_id' => $entity->villageId,
            'postal_code_id' => $entity->postalCodeId,
            'status' => $entity->status->value,
        ];
    }

    private function toEntity(stdClass $row): LegalEntity
    {
        $nullableInt = static fn (mixed $value): ?int => $value === null ? null : (int) $value;

        return new LegalEntity(
            (int) $row->id,
            (int) $row->group_id,
            (string) $row->legal_name,
            LegalForm::from((string) $row->legal_form),
            $row->npwp === null ? null : (string) $row->npwp,
            $row->nib === null ? null : (string) $row->nib,
            $row->address_line === null ? null : (string) $row->address_line,
            $nullableInt($row->province_id),
            $nullableInt($row->city_id),
            $nullableInt($row->subdistrict_id),
            $nullableInt($row->village_id),
            $nullableInt($row->postal_code_id),
            LegalEntityStatus::from((string) $row->status),
        );
    }
}
