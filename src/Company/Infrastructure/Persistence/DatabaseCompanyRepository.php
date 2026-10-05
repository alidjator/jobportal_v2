<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Clock;

final readonly class DatabaseCompanyRepository implements CompanyRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function findById(int $id): ?Company
    {
        $row = $this->connection->table('company_companies')->where('id', $id)->first();

        return $row === null ? null : new Company(
            (int) $row->id,
            (string) $row->name,
            (string) $row->slug,
            CompanyStatus::from($row->status),
            (int) $row->industry_id,
            (int) $row->organization_type_id,
            $row->created_by_admin_id === null ? null : (int) $row->created_by_admin_id,
        );
    }

    public function slugExists(string $slug): bool
    {
        return $this->connection->table('company_companies')->where('slug', $slug)->exists();
    }

    public function lock(int $companyId): void
    {
        $this->connection->table('company_companies')->where('id', $companyId)->lockForUpdate()->first(['id']);
    }

    public function save(Company $company): Company
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);

        $id = $this->connection->table('company_companies')->insertGetId([
            'name' => $company->name(),
            'slug' => $company->slug(),
            'status' => $company->status()->value,
            'industry_id' => $company->industryId(),
            'organization_type_id' => $company->organizationTypeId(),
            'created_by_admin_id' => $company->createdByAdminId(),
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return new Company(
            (int) $id, $company->name(), $company->slug(), $company->status(), $company->industryId(),
            $company->organizationTypeId(), $company->createdByAdminId(),
        );
    }
}
