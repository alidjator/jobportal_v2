<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\Erasure\ErasureLedger;
use Src\Shared\Application\Accounts\ErasureReceipts;
use Src\Shared\Application\Clock;

final readonly class DatabaseErasureLedger implements ErasureLedger, ErasureReceipts
{
    private const string RECEIPTS = 'identity_erasure_receipts';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function record(string $step, int $userId): void
    {
        $this->connection->table(self::RECEIPTS)->upsert(
            [['user_id' => $userId, 'step' => $step, 'completed_at' => $this->clock->now()]],
            ['user_id', 'step'],
            ['completed_at'],
        );
    }

    public function anonymizedBetween(DateTimeImmutable $from, DateTimeImmutable $to, int $afterId, int $limit): array
    {
        return $this->connection->table('identity_users')
            ->whereNotNull('anonymized_at')
            ->where('anonymized_at', '>=', $from)
            ->where('anonymized_at', '<=', $to)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function finishedSteps(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $steps = [];

        foreach ($this->connection->table(self::RECEIPTS)->whereIn('user_id', $userIds)->get(['user_id', 'step']) as $row) {
            $steps[(int) $row->user_id][] = (string) $row->step;
        }

        return $steps;
    }
}
