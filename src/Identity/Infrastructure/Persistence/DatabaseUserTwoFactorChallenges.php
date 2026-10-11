<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\TwoFactor\UserTwoFactorChallenge;
use Src\Identity\Application\TwoFactor\UserTwoFactorChallenges;

final readonly class DatabaseUserTwoFactorChallenges implements UserTwoFactorChallenges
{
    private const string TABLE = 'identity_user_two_factor_challenges';

    public function __construct(private ConnectionInterface $connection) {}

    public function issue(int $userId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->whereNull('used_at')->delete();

        $this->connection->table(self::TABLE)->insert([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);
    }

    public function findUsable(string $tokenHash, DateTimeImmutable $now): ?UserTwoFactorChallenge
    {
        $row = $this->connection->table(self::TABLE)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->first(['id', 'user_id']);

        return $row === null ? null : new UserTwoFactorChallenge((int) $row->id, (int) $row->user_id);
    }

    public function consume(int $challengeId, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('id', $challengeId)->update(['used_at' => $now]);
    }

    public function deleteForUser(int $userId): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->delete();
    }

    public function purge(DateTimeImmutable $now): int
    {
        return $this->connection->table(self::TABLE)
            ->where(fn ($query) => $query->where('expires_at', '<=', $now)->orWhereNotNull('used_at'))
            ->delete();
    }
}
