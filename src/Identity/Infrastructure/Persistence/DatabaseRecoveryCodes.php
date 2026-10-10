<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\TwoFactor\RecoveryCodes;

final readonly class DatabaseRecoveryCodes implements RecoveryCodes
{
    private const string TABLE = 'identity_admin_recovery_codes';

    public function __construct(private ConnectionInterface $connection) {}

    public function replace(int $adminId, array $hashes, DateTimeImmutable $now): void
    {
        $this->deleteForAdmin($adminId);

        $this->connection->table(self::TABLE)->insert(array_map(
            fn (string $hash): array => ['admin_id' => $adminId, 'code_hash' => $hash, 'created_at' => $now],
            $hashes,
        ));
    }

    public function consume(int $adminId, string $hash, DateTimeImmutable $now): bool
    {
        return $this->connection->table(self::TABLE)
            ->where('admin_id', $adminId)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->update(['used_at' => $now]) > 0;
    }

    public function deleteForAdmin(int $adminId): void
    {
        $this->connection->table(self::TABLE)->where('admin_id', $adminId)->delete();
    }
}
