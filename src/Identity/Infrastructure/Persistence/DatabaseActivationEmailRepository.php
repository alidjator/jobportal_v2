<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Identity\Domain\ActivationEmailRepository;
use Src\Identity\Domain\ActivationKind;
use Src\Identity\Domain\ActivationVariant;

final readonly class DatabaseActivationEmailRepository implements ActivationEmailRepository
{
    private const string TABLE = 'identity_activation_emails';

    public function __construct(private ConnectionInterface $connection) {}

    public function reserve(int $userId, ActivationKind $kind, ActivationVariant $variant, string $batch, DateTimeImmutable $at): bool
    {
        return $this->connection->table(self::TABLE)->insertOrIgnore([
            'user_id' => $userId,
            'kind' => $kind->value,
            'variant' => $variant->value,
            'batch' => $batch,
            'sent_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]) === 1;
    }

    public function release(int $userId, ActivationKind $kind): void
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->where('kind', $kind->value)->delete();
    }

    public function sentAt(ActivationKind $kind, array $userIds): array
    {
        $sent = [];

        foreach ($this->connection->table(self::TABLE)->where('kind', $kind->value)->whereIn('user_id', $userIds)->get(['user_id', 'sent_at']) as $row) {
            $sent[(int) $row->user_id] = new DateTimeImmutable((string) $row->sent_at, new DateTimeZone('UTC'));
        }

        return $sent;
    }

    public function latestBatch(array $variants): ?array
    {
        $variantValues = array_map(static fn (ActivationVariant $variant): string => $variant->value, $variants);
        $latest = $this->connection->table(self::TABLE)
            ->whereIn('kind', [ActivationKind::Activation->value, ActivationKind::Reminder->value])
            ->whereIn('variant', $variantValues)
            ->orderByDesc('id')
            ->value('batch');

        if ($latest === null) {
            return null;
        }

        return [
            'batch' => (string) $latest,
            'userIds' => $this->connection->table(self::TABLE)->where('batch', $latest)->whereIn('variant', $variantValues)->pluck('user_id')->map(static fn (mixed $id): int => (int) $id)->all(),
        ];
    }
}
