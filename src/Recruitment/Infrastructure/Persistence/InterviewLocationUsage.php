<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Company\CompanyLocationUsage;

/** An interview that points to a work location keeps it from being deleted (the interview keeps showing it after the location is deactivated). */
final readonly class InterviewLocationUsage implements CompanyLocationUsage
{
    public function __construct(private ConnectionInterface $connection) {}

    public function holds(int $companyId, int $locationId): bool
    {
        // A location id belongs to one company, so the id alone decides.
        return $this->connection->table('recruitment_interviews')->where('location_id', $locationId)->exists();
    }
}
