<?php

namespace Src\JobAlert\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\JobAlert\Domain\AlertDeliveryRepository;

final readonly class DatabaseAlertDeliveryRepository implements AlertDeliveryRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function claim(int $alertId, int $jobId): bool
    {
        return $this->connection->table('jobalert_deliveries')->insertOrIgnore(['alert_id' => $alertId, 'job_id' => $jobId]) === 1;
    }
}
