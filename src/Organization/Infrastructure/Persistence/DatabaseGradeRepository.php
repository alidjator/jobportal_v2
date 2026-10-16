<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\Grade;
use Src\Organization\Domain\GradeRepository;
use Src\Organization\Domain\ItemStatus;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseGradeRepository implements GradeRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $id): ?Grade
    {
        $row = $this->connection->table('organization_grades')->where('id', $id)->first();

        return $row === null ? null : $this->toGrade($row);
    }

    public function create(Grade $grade): Grade
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('organization_grades')->insertGetId([...$this->columns($grade), 'group_id' => $grade->groupId, 'code' => $grade->code, 'created_at' => $now, 'updated_at' => $now]);

        return $this->find($id) ?? throw new \LogicException("Grade {$id} was just created.");
    }

    public function save(Grade $grade): void
    {
        $this->connection->table('organization_grades')->where('id', $grade->id)->update([...$this->columns($grade), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function ofGroup(int $groupId): array
    {
        return $this->connection->table('organization_grades')->where('group_id', $groupId)->orderBy('rank')->orderBy('code')->get()
            ->map(fn (stdClass $row): Grade => $this->toGrade($row))
            ->all();
    }

    public function codeTaken(int $groupId, string $code): bool
    {
        return $this->connection->table('organization_grades')->where('group_id', $groupId)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists();
    }

    public function inUse(int $id, DateTimeImmutable $today): bool
    {
        return $this->connection->table('organization_position_versions as version')
            ->join('organization_positions as position', 'position.id', '=', 'version.position_id')
            ->where('position.status', ItemStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('version.valid_to')->orWhere('version.valid_to', '>=', $today->format('Y-m-d')))
            ->where(fn ($query) => $query->where('version.min_grade_id', $id)->orWhere('version.max_grade_id', $id))
            ->exists();
    }

    /** @return array<string, mixed> */
    private function columns(Grade $grade): array
    {
        return ['name' => $grade->name, 'rank' => $grade->rank, 'status' => $grade->status->value];
    }

    private function toGrade(stdClass $row): Grade
    {
        return new Grade((int) $row->id, (int) $row->group_id, (string) $row->code, (string) $row->name, (int) $row->rank, ItemStatus::from((string) $row->status));
    }
}
