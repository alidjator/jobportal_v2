<?php

namespace Src\Workforce\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Company\CompanyLocationUsage;

/** A requisition that points to a work location keeps it from being deleted. */
final readonly class RequisitionLocationUsage implements CompanyLocationUsage
{
    public function __construct(private ConnectionInterface $connection) {}

    public function holds(int $companyId, int $locationId): bool
    {
        // A location id belongs to one company, so the id alone decides.
        return $this->connection->table('workforce_requisitions')->where('location_id', $locationId)->exists();
    }
}
