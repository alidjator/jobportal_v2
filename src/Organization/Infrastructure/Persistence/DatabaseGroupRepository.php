<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\Group;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\GroupStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseGroupRepository implements GroupRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id): ?Group
    {
        $row = $this->connection->table('organization_groups')->where('id', $id)->first();

        return $row === null ? null : $this->toGroup($row);
    }

    public function lock(int $id): void
    {
        $this->connection->table('organization_groups')->where('id', $id)->lockForUpdate()->first(['id']);
    }

    public function create(Group $group): Group
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $id = (int) $this->connection->table('organization_groups')->insertGetId([...$this->columns($group), 'created_at' => $now, 'updated_at' => $now]);

        return $this->find($id) ?? throw new \LogicException("Group {$id} was just created.");
    }

    public function save(Group $group): void
    {
        $this->connection->table('organization_groups')->where('id', $group->id)->update([...$this->columns($group), 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return $this->connection->table('organization_groups')
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function page(PageRequest $request): Page
    {
        $total = $this->connection->table('organization_groups')->count();
        $rows = $this->connection->table('organization_groups')->orderBy('name')->orderBy('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page($rows->map(fn (stdClass $row): Group => $this->toGroup($row))->all(), $total, $request);
    }

    /** @return array<string, mixed> */
    private function columns(Group $group): array
    {
        return [
            'name' => $group->name,
            'status' => $group->status->value,
            'status_reason' => $group->statusReason,
            'suspended_at' => $group->suspendedAt?->format(self::TIME_FORMAT),
        ];
    }

    private function toGroup(stdClass $row): Group
    {
        return new Group(
            (int) $row->id,
            (string) $row->name,
            GroupStatus::from((string) $row->status),
            $row->status_reason === null ? null : (string) $row->status_reason,
            $row->suspended_at === null ? null : new DateTimeImmutable((string) $row->suspended_at, new DateTimeZone('UTC')),
            $row->created_at === null ? null : new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
