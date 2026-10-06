<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanySummary;

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

    public function summaries(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $summaries = [];

        foreach ($this->connection->table('company_companies')->whereIn('id', $companyIds)->get(['id', 'name', 'slug', 'status']) as $row) {
            $summaries[(int) $row->id] = new CompanySummary((int) $row->id, (string) $row->name, (string) $row->slug, (string) $row->status);
        }

        return $summaries;
    }

    public function idsByName(string $text, int $limit): array
    {
        // The name column uses a case-insensitive collation; `%`, `_` and `\` typed by the caller are searched for literally.
        return array_map('intval', $this->connection->table('company_companies')
            ->where('name', 'like', '%'.addcslashes($text, '%_\\').'%')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all());
    }

    public function idsAfter(int $afterId, int $limit): array
    {
        return array_map('intval', $this->connection->table('company_companies')->where('id', '>', $afterId)->orderBy('id')->limit($limit)->pluck('id')->all());
    }
}
