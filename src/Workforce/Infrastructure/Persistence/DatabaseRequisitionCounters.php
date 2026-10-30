<?php

namespace Src\Workforce\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Workforce\Domain\RequisitionCounters;

final readonly class DatabaseRequisitionCounters implements RequisitionCounters
{
    private const string TABLE = 'workforce_requisition_counters';

    public function __construct(private ConnectionInterface $connection) {}

    public function next(int $companyId, string $series): int
    {
        $this->connection->table(self::TABLE)->insertOrIgnore(['company_id' => $companyId, 'series' => $series, 'last_number' => 0]);

        $last = (int) $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('series', $series)->lockForUpdate()->value('last_number');
        $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('series', $series)->update(['last_number' => $last + 1]);

        return $last + 1;
    }
}
