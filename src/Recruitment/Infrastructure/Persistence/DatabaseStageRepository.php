<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class DatabaseStageRepository implements StageRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function companyHasStages(int $companyId): bool
    {
        return $this->connection->table('recruitment_stages')->where('company_id', $companyId)->exists();
    }

    public function addMany(array $stages, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_stages')->insert(array_map(fn (Stage $stage): array => $this->row($stage, $at), $stages));
    }

    public function lockCompany(int $companyId): void
    {
        $this->connection->table('recruitment_stages')->where('company_id', $companyId)->lockForUpdate()->get(['id']);
    }

    public function forCompany(int $companyId): array
    {
        return $this->connection->table('recruitment_stages')
            ->where('company_id', $companyId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'company_id', 'type', 'name', 'position', 'is_default'])
            ->map(static fn (object $row): Stage => new Stage(
                (int) $row->company_id, StageType::from($row->type), (string) $row->name, (int) $row->position, (bool) $row->is_default, (int) $row->id,
            ))
            ->all();
    }

    public function add(Stage $stage, DateTimeImmutable $at): int
    {
        try {
            return (int) $this->connection->table('recruitment_stages')->insertGetId($this->row($stage, $at));
        } catch (UniqueConstraintViolationException) {
            throw self::nameTaken();
        }
    }

    public function rename(int $id, string $name, DateTimeImmutable $at): void
    {
        try {
            $this->connection->table('recruitment_stages')->where('id', $id)->update(['name' => $name, 'updated_at' => $at->format(self::TIME_FORMAT)]);
        } catch (UniqueConstraintViolationException) {
            throw self::nameTaken();
        }
    }

    public function delete(int $id): void
    {
        $this->connection->table('recruitment_stages')->where('id', $id)->delete();
    }

    public function savePositions(array $positions): void
    {
        foreach ($positions as $id => $position) {
            $this->connection->table('recruitment_stages')->where('id', $id)->update(['position' => $position]);
        }
    }

    /** @return array<string, mixed> */
    private function row(Stage $stage, DateTimeImmutable $at): array
    {
        $time = $at->format(self::TIME_FORMAT);

        return [
            'company_id' => $stage->companyId,
            'type' => $stage->type->value,
            'name' => $stage->name,
            'position' => $stage->position,
            'is_default' => $stage->isDefault,
            'created_at' => $time,
            'updated_at' => $time,
        ];
    }

    private static function nameTaken(): StateConflict
    {
        return new StateConflict('Another stage of this company already has this name.', 'stage-name-taken');
    }
}
