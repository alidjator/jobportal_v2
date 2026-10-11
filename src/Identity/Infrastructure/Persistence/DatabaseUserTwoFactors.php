<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\TwoFactor\UserRecoveryCodes;
use Src\Identity\Application\TwoFactor\UserTwoFactor;
use Src\Identity\Application\TwoFactor\UserTwoFactorChallenges;
use Src\Identity\Application\TwoFactor\UserTwoFactors;

final readonly class DatabaseUserTwoFactors implements UserTwoFactors
{
    private const string TABLE = 'identity_user_two_factor';

    public function __construct(
        private ConnectionInterface $connection,
        private Encrypter $encrypter,
        private UserRecoveryCodes $recoveryCodes,
        private UserTwoFactorChallenges $challenges,
    ) {}

    public function find(int $userId): ?UserTwoFactor
    {
        $row = $this->connection->table(self::TABLE)->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return new UserTwoFactor(
            $this->encrypter->decryptString((string) $row->secret),
            $row->confirmed_at === null ? null : new DateTimeImmutable((string) $row->confirmed_at, new DateTimeZone('UTC')),
            $row->last_step === null ? null : (int) $row->last_step,
        );
    }

    public function begin(int $userId, string $secret, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->upsert(
            [['user_id' => $userId, 'secret' => $this->encrypter->encryptString($secret), 'confirmed_at' => null, 'last_step' => null, 'created_at' => $now, 'updated_at' => $now]],
            ['user_id'],
            ['secret', 'confirmed_at', 'last_step', 'updated_at'],
        );
    }

    public function confirm(int $userId, int $step, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->update(['confirmed_at' => $now, 'last_step' => $step, 'updated_at' => $now]);
    }

    public function recordStep(int $userId, int $step, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->update(['last_step' => $step, 'updated_at' => $now]);
    }

    public function reset(int $userId): void
    {
        $this->recoveryCodes->deleteForUser($userId);
        $this->challenges->deleteForUser($userId);
        $this->connection->table(self::TABLE)->where('user_id', $userId)->delete();
    }
}
