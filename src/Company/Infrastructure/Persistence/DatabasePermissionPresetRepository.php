<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Company\Domain\PermissionPreset;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabasePermissionPresetRepository implements PermissionPresetRepository
{
    private const string TABLE = 'company_permission_presets';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(PermissionPreset $preset): PermissionPreset
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([
                'company_id' => $preset->companyId,
                'name' => $preset->name,
                'permissions' => json_encode($preset->permissions, JSON_THROW_ON_ERROR),
                'created_by_user_id' => $preset->createdByUserId,
                'created_at' => $this->format($preset->createdAt),
                'updated_at' => $this->format($preset->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->taken();
        }

        return new PermissionPreset($preset->companyId, $preset->name, $preset->permissions, $preset->createdByUserId, $preset->createdAt, $preset->updatedAt, $id);
    }

    public function save(PermissionPreset $preset): void
    {
        try {
            $this->connection->table(self::TABLE)->where('company_id', $preset->companyId)->where('id', $preset->id)->update([
                'name' => $preset->name,
                'permissions' => json_encode($preset->permissions, JSON_THROW_ON_ERROR),
                'updated_at' => $this->format($preset->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->taken();
        }
    }

    public function find(int $companyId, int $id, bool $forUpdate = false): ?PermissionPreset
    {
        $query = $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toPreset($row);
    }

    public function ofCompany(int $companyId): array
    {
        return array_map(
            fn (stdClass $row): PermissionPreset => $this->toPreset($row),
            $this->connection->table(self::TABLE)->where('company_id', $companyId)->orderByRaw('lower(name)')->orderBy('id')->get()->all(),
        );
    }

    public function countOfCompany(int $companyId): int
    {
        return $this->connection->table(self::TABLE)->where('company_id', $companyId)->count();
    }

    public function delete(int $companyId, int $id): void
    {
        $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('id', $id)->delete();
    }

    private function taken(): StateConflict
    {
        return new StateConflict('A permission preset with this name already exists.', 'permission-preset-name-taken');
    }

    private function toPreset(stdClass $row): PermissionPreset
    {
        /** @var list<string> $permissions */
        $permissions = json_decode((string) $row->permissions, true, flags: JSON_THROW_ON_ERROR);

        return new PermissionPreset(
            (int) $row->company_id,
            (string) $row->name,
            $permissions,
            (int) $row->created_by_user_id,
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
