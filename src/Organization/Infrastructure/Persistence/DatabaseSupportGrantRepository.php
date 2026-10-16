<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Organization\Domain\SupportGrant;
use Src\Organization\Domain\SupportGrantRepository;
use stdClass;

final readonly class DatabaseSupportGrantRepository implements SupportGrantRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $id): ?SupportGrant
    {
        $row = $this->connection->table('organization_support_grants')->where('id', $id)->first();

        return $row === null ? null : $this->toGrant($row);
    }

    public function create(SupportGrant $grant): SupportGrant
    {
        $id = (int) $this->connection->table('organization_support_grants')->insertGetId([
            'group_id' => $grant->groupId,
            'admin_id' => $grant->adminId,
            'granted_by_user_id' => $grant->grantedByUserId,
            'expires_at' => $grant->expiresAt->format(self::TIME_FORMAT),
            'created_at' => $grant->createdAt->format(self::TIME_FORMAT),
        ]);

        return $this->find($id) ?? throw new \LogicException("Support grant {$id} was just created.");
    }

    public function save(SupportGrant $grant): void
    {
        $this->connection->table('organization_support_grants')->where('id', $grant->id)->update([
            'revoked_at' => $grant->revokedAt?->format(self::TIME_FORMAT),
            'revoked_by_user_id' => $grant->revokedByUserId,
        ]);
    }

    public function activeFor(int $groupId, int $adminId, DateTimeImmutable $now): ?SupportGrant
    {
        $row = $this->connection->table('organization_support_grants')
            ->where('group_id', $groupId)->where('admin_id', $adminId)
            ->whereNull('revoked_at')->where('expires_at', '>', $now->format(self::TIME_FORMAT))
            ->orderByDesc('id')->first();

        return $row === null ? null : $this->toGrant($row);
    }

    public function ofGroupSince(int $groupId, DateTimeImmutable $since): array
    {
        return $this->connection->table('organization_support_grants')
            ->where('group_id', $groupId)->where('created_at', '>=', $since->format(self::TIME_FORMAT))
            ->orderByDesc('id')->get()
            ->map(fn (stdClass $row): SupportGrant => $this->toGrant($row))
            ->all();
    }

    public function activeOfAdmin(int $adminId, DateTimeImmutable $now): array
    {
        return $this->connection->table('organization_support_grants')
            ->where('admin_id', $adminId)->whereNull('revoked_at')->where('expires_at', '>', $now->format(self::TIME_FORMAT))
            ->orderBy('expires_at')->orderBy('id')->get()
            ->map(fn (stdClass $row): SupportGrant => $this->toGrant($row))
            ->all();
    }

    private function toGrant(stdClass $row): SupportGrant
    {
        $time = static fn (mixed $value): DateTimeImmutable => new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new SupportGrant(
            (int) $row->id,
            (int) $row->group_id,
            (int) $row->admin_id,
            (int) $row->granted_by_user_id,
            $time($row->expires_at),
            $row->revoked_at === null ? null : $time($row->revoked_at),
            $row->revoked_by_user_id === null ? null : (int) $row->revoked_by_user_id,
            $time($row->created_at),
        );
    }
}
