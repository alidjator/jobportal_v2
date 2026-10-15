<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\TwoFactor\UserRecoveryCodes;

final readonly class DatabaseUserRecoveryCodes implements UserRecoveryCodes
{
    private const string TABLE = 'identity_user_recovery_codes';

    public function __construct(private ConnectionInterface $connection) {}

    public function replace(int $userId, array $hashes, DateTimeImmutable $now): void
    {
        $this->deleteForUser($userId);

        $this->connection->table(self::TABLE)->insert(array_map(
            fn (string $hash): array => ['user_id' => $userId, 'code_hash' => $hash, 'created_at' => $now],
            $hashes,
        ));
    }

    public function consume(int $userId, string $hash, DateTimeImmutable $now): bool
    {
        return $this->connection->table(self::TABLE)
            ->where('user_id', $userId)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->update(['used_at' => $now]) > 0;
    }

    public function remaining(int $userId): int
    {
        return $this->connection->table(self::TABLE)->where('user_id', $userId)->whereNull('used_at')->count();
    }

    public function deleteForUser(int $userId): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->delete();
    }
}
