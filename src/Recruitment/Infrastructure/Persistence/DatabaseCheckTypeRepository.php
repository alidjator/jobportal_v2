<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Recruitment\Domain\CheckKind;
use Src\Recruitment\Domain\CheckType;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseCheckTypeRepository implements CheckTypeRepository
{
    private const string TABLE = 'recruitment_check_types';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(CheckType $type): CheckType
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([
                'company_id' => $type->companyId,
                'name' => $type->name,
                'kind' => $type->kind->value,
                'instructions' => $type->instructions,
                'requires_consent' => $type->requiresConsent,
                'is_active' => $type->isActive,
                'created_at' => $this->format($type->createdAt),
                'updated_at' => $this->format($type->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->taken();
        }

        return new CheckType($type->companyId, $type->name, $type->kind, $type->instructions, $type->requiresConsent, $type->isActive, $type->createdAt, $type->updatedAt, $id);
    }

    public function save(CheckType $type): void
    {
        try {
            $this->connection->table(self::TABLE)->where('company_id', $type->companyId)->where('id', $type->id)->update([
                'name' => $type->name,
                'instructions' => $type->instructions,
                'requires_consent' => $type->requiresConsent,
                'is_active' => $type->isActive,
                'updated_at' => $this->format($type->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->taken();
        }
    }

    public function find(int $companyId, int $id, bool $forUpdate = false): ?CheckType
    {
        $query = $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toType($row);
    }

    public function ofCompany(int $companyId): array
    {
        return array_map(
            fn (stdClass $row): CheckType => $this->toType($row),
            $this->connection->table(self::TABLE)->where('company_id', $companyId)->orderByRaw('lower(name)')->orderBy('id')->get()->all(),
        );
    }

    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $types = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('id', $ids)->get() as $row) {
            $types[(int) $row->id] = $this->toType($row);
        }

        return $types;
    }

    private function taken(): StateConflict
    {
        return new StateConflict('A check type with this name already exists.', 'check-type-name-taken');
    }

    private function toType(stdClass $row): CheckType
    {
        return new CheckType(
            (int) $row->company_id,
            (string) $row->name,
            CheckKind::from((string) $row->kind),
            $row->instructions,
            (bool) $row->requires_consent,
            (bool) $row->is_active,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
