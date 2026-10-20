<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Domain\EmailChange;
use Src\Identity\Domain\EmailChangeRepository;

final readonly class DatabaseEmailChangeRepository implements EmailChangeRepository
{
    private const string TABLE = 'identity_email_changes';

    public function __construct(private ConnectionInterface $connection) {}

    public function findByUser(int $userId): ?EmailChange
    {
        $row = $this->connection->table(self::TABLE)->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return new EmailChange(
            (int) $row->user_id, $row->new_email, $row->new_code_hash, $row->old_code_hash, (int) $row->attempts,
            new DateTimeImmutable($row->expires_at, new DateTimeZone('UTC')), new DateTimeImmutable($row->created_at, new DateTimeZone('UTC')),
        );
    }

    public function replace(EmailChange $change): void
    {
        $this->delete($change->userId);

        $this->connection->table(self::TABLE)->insert([
            'user_id' => $change->userId,
            'new_email' => $change->newEmail,
            'new_code_hash' => $change->newCodeHash,
            'old_code_hash' => $change->oldCodeHash,
            'attempts' => $change->attempts(),
            'expires_at' => $change->expiresAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'created_at' => $change->createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }

    public function saveAttempts(EmailChange $change): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $change->userId)->update(['attempts' => $change->attempts()]);
    }

    public function delete(int $userId): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->delete();
    }
}
