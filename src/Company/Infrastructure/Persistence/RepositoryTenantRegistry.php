<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Company\TenantRegistry;

final readonly class RepositoryTenantRegistry implements TenantRegistry
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function legalEntityOf(int $companyId): ?int
    {
        $id = $this->connection->table('company_companies')->where('id', $companyId)->value('legal_entity_id');

        return $id === null ? null : (int) $id;
    }

    public function tenantsOf(array $legalEntityIds): array
    {
        if ($legalEntityIds === []) {
            return [];
        }

        $tenants = [];

        foreach ($this->connection->table('company_companies')->whereIn('legal_entity_id', $legalEntityIds)->get(['id', 'legal_entity_id']) as $row) {
            $tenants[(int) $row->legal_entity_id] = (int) $row->id;
        }

        return $tenants;
    }

    public function linkedCount(): int
    {
        return $this->connection->table('company_companies')->whereNotNull('legal_entity_id')->count();
    }

    public function exists(int $companyId): bool
    {
        return $this->connection->table('company_companies')->where('id', $companyId)->exists();
    }

    public function link(int $companyId, int $legalEntityId): void
    {
        $this->connection->table('company_companies')->where('id', $companyId)->update(['legal_entity_id' => $legalEntityId]);
    }

    public function unlink(int $companyId): void
    {
        $this->connection->table('company_companies')->where('id', $companyId)->update(['legal_entity_id' => null]);
    }

    public function suspendForGroup(array $companyIds, string $reason, DateTimeImmutable $at): void
    {
        if ($companyIds === []) {
            return;
        }

        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('company_companies')
            ->whereIn('id', $companyIds)
            ->where('status', CompanyStatus::Active->value)
            ->update(['status' => CompanyStatus::Suspended->value, 'suspended_at' => $time, 'suspension_reason' => $reason, 'suspended_by_group_at' => $time, 'updated_at' => $time]);
    }

    public function restoreFromGroup(array $companyIds): void
    {
        if ($companyIds === []) {
            return;
        }

        $this->connection->table('company_companies')
            ->whereIn('id', $companyIds)
            ->whereNotNull('suspended_by_group_at')
            ->update(['status' => CompanyStatus::Active->value, 'suspended_at' => null, 'suspension_reason' => null, 'suspended_by_group_at' => null]);
    }
}
