<?php

namespace Src\Newsletter\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Newsletter\Domain\Delivery;
use Src\Newsletter\Domain\DeliveryRepository;
use Src\Newsletter\Domain\DeliveryStatus;

final readonly class DatabaseDeliveryRepository implements DeliveryRepository
{
    private const string TABLE = 'newsletter_deliveries';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function queue(int $issueId, array $subscriberIds, DateTimeImmutable $now): array
    {
        if ($subscriberIds === []) {
            return [];
        }

        $time = $now->format(self::TIME_FORMAT);

        $this->connection->table(self::TABLE)->insertOrIgnore(array_map(
            static fn (int $subscriberId): array => ['issue_id' => $issueId, 'subscriber_id' => $subscriberId, 'status' => DeliveryStatus::Queued->value, 'created_at' => $time, 'updated_at' => $time],
            $subscriberIds,
        ));

        return array_map('intval', $this->connection->table(self::TABLE)
            ->where('issue_id', $issueId)
            ->whereIn('subscriber_id', $subscriberIds)
            ->where('status', DeliveryStatus::Queued->value)
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    public function find(int $id): ?Delivery
    {
        $row = $this->connection->table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : new Delivery((int) $row->id, (int) $row->issue_id, (int) $row->subscriber_id, DeliveryStatus::from((string) $row->status));
    }

    public function markSent(int $id, DateTimeImmutable $now): void
    {
        $time = $now->format(self::TIME_FORMAT);
        $this->connection->table(self::TABLE)->where('id', $id)->update(['status' => DeliveryStatus::Sent->value, 'sent_at' => $time, 'updated_at' => $time]);
    }

    public function markFailed(int $id, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->update(['status' => DeliveryStatus::Failed->value, 'updated_at' => $now->format(self::TIME_FORMAT)]);
    }

    public function countQueued(int $issueId): int
    {
        return $this->connection->table(self::TABLE)->where('issue_id', $issueId)->where('status', DeliveryStatus::Queued->value)->count();
    }
}
