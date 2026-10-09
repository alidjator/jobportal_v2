<?php

namespace Src\Matching\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Matching\Domain\DeliveryRepository;

final readonly class DatabaseDeliveryRepository implements DeliveryRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function sentJobIds(int $userId, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        return $this->connection->table('matching_deliveries')->where('user_id', $userId)->whereIn('job_id', $jobIds)->pluck('job_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function hasSentSince(int $userId, DateTimeImmutable $since): bool
    {
        return $this->connection->table('matching_deliveries')->where('user_id', $userId)->where('created_at', '>=', $this->format($since))->exists();
    }

    public function record(int $userId, array $jobIds, DateTimeImmutable $at): void
    {
        $this->connection->table('matching_deliveries')->insertOrIgnore(array_map(
            fn (int $jobId): array => ['user_id' => $userId, 'job_id' => $jobId, 'created_at' => $this->format($at)],
            $jobIds,
        ));
    }

    public function deleteOfUser(int $userId): void
    {
        $this->connection->table('matching_deliveries')->where('user_id', $userId)->delete();
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
