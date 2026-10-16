<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Organization\Domain\ItemStatus;
use Src\Organization\Domain\Position;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\PositionVersion;
use Src\Organization\Domain\Timeline;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabasePositionRepository implements PositionRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id): ?Position
    {
        $row = $this->connection->table('organization_positions')->where('id', $id)->first();

        return $row === null ? null : $this->toPosition($row);
    }

    public function create(Position $position): Position
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('organization_positions')->insertGetId([
            'legal_entity_id' => $position->legalEntityId, 'code' => $position->code, 'status' => $position->status->value, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException("Position {$id} was just created.");
    }

    public function save(Position $position): void
    {
        $this->connection->table('organization_positions')->where('id', $position->id)->update(['status' => $position->status->value, 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofEntity(int $legalEntityId): array
    {
        return $this->connection->table('organization_positions')->where('legal_entity_id', $legalEntityId)->orderBy('code')->get()
            ->map(fn (stdClass $row): Position => $this->toPosition($row))
            ->all();
    }

    public function codeTaken(int $legalEntityId, string $code): bool
    {
        return $this->connection->table('organization_positions')->where('legal_entity_id', $legalEntityId)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists();
    }

    public function versionsOf(int $positionId): array
    {
        return $this->timelinesFor($this->connection->table('organization_position_versions')->where('position_id', $positionId))[$positionId] ?? [];
    }

    public function timelinesOfEntity(int $legalEntityId): array
    {
        return $this->timelinesFor(
            $this->connection->table('organization_position_versions as version')
                ->join('organization_positions as position', 'position.id', '=', 'version.position_id')
                ->where('position.legal_entity_id', $legalEntityId)
                ->select('version.*'),
        );
    }

    public function replaceVersions(int $positionId, array $versions): void
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $this->connection->transaction(function () use ($positionId, $versions, $now): void {
            $this->connection->table('organization_position_versions')->where('position_id', $positionId)->delete();
            $this->connection->table('organization_position_versions')->insert(array_map(static fn (PositionVersion $version): array => [
                'position_id' => $positionId,
                'valid_from' => $version->validFrom->format('Y-m-d'),
                'valid_to' => $version->validTo?->format('Y-m-d'),
                'title' => $version->title,
                'unit_id' => $version->unitId,
                'min_grade_id' => $version->minGradeId,
                'max_grade_id' => $version->maxGradeId,
                'reports_to_position_id' => $version->reportsToPositionId,
                'job_role_id' => $version->jobRoleId,
                'created_at' => $now,
            ], $versions));
        });
    }

    public function inUse(int $id, DateTimeImmutable $today): bool
    {
        return $this->connection->table('organization_position_versions as version')
            ->join('organization_positions as position', 'position.id', '=', 'version.position_id')
            ->where('position.status', ItemStatus::Active->value)
            ->where('version.reports_to_position_id', $id)
            ->where(fn ($query) => $query->whereNull('version.valid_to')->orWhere('version.valid_to', '>=', $today->format('Y-m-d')))
            ->exists();
    }

    /**
     * @param  Builder  $query
     * @return array<int, list<PositionVersion>>
     */
    private function timelinesFor($query): array
    {
        $nullableInt = static fn (mixed $value): ?int => $value === null ? null : (int) $value;
        $byPosition = [];

        foreach ($query->get() as $row) {
            $byPosition[(int) $row->position_id][] = new PositionVersion(
                (int) $row->position_id,
                new DateTimeImmutable((string) $row->valid_from),
                $row->valid_to === null ? null : new DateTimeImmutable((string) $row->valid_to),
                (string) $row->title,
                (int) $row->unit_id,
                $nullableInt($row->min_grade_id),
                $nullableInt($row->max_grade_id),
                $nullableInt($row->reports_to_position_id),
                $nullableInt($row->job_role_id),
            );
        }

        return array_map(Timeline::sorted(...), $byPosition);
    }

    private function toPosition(stdClass $row): Position
    {
        return new Position((int) $row->id, (int) $row->legal_entity_id, (string) $row->code, ItemStatus::from((string) $row->status));
    }
}
