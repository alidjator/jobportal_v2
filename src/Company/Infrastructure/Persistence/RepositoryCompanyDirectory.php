<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Company\CompanyDirectory;

final readonly class RepositoryCompanyDirectory implements CompanyDirectory
{
    public function __construct(private ConnectionInterface $connection) {}

    public function exists(int $companyId): bool
    {
        return $this->connection->table('company_companies')->where('id', $companyId)->exists();
    }

    public function isActive(int $companyId): bool
    {
        return $this->connection->table('company_companies')->where('id', $companyId)->where('status', CompanyStatus::Active->value)->exists();
    }
}
