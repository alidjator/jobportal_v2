<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\TwoFactor\TwoFactorChallenge;
use Src\Identity\Application\TwoFactor\TwoFactorChallenges;

final readonly class DatabaseTwoFactorChallenges implements TwoFactorChallenges
{
    private const string TABLE = 'identity_admin_two_factor_challenges';

    public function __construct(private ConnectionInterface $connection) {}

    public function issue(int $adminId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('admin_id', $adminId)->whereNull('used_at')->delete();

        $this->connection->table(self::TABLE)->insert([
            'admin_id' => $adminId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);
    }

    public function findUsable(string $tokenHash, DateTimeImmutable $now): ?TwoFactorChallenge
    {
        $row = $this->connection->table(self::TABLE)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->first(['id', 'admin_id']);

        return $row === null ? null : new TwoFactorChallenge((int) $row->id, (int) $row->admin_id);
    }

    public function consume(int $challengeId, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('id', $challengeId)->update(['used_at' => $now]);
    }

    public function deleteForAdmin(int $adminId): void
    {
        $this->connection->table(self::TABLE)->where('admin_id', $adminId)->delete();
    }

    public function purge(DateTimeImmutable $now): int
    {
        return $this->connection->table(self::TABLE)
            ->where(fn ($query) => $query->where('expires_at', '<=', $now)->orWhereNotNull('used_at'))
            ->delete();
    }
}
