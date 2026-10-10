<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\URL;
use Src\Company\Application\Images\CompanyImageFile;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Company\PublicCompanyCard;

final readonly class RepositoryPublicCompanies implements PublicCompanies
{
    public function __construct(private ConnectionInterface $connection) {}

    public function card(int $companyId): ?PublicCompanyCard
    {
        return $this->cards([$companyId])[$companyId] ?? null;
    }

    public function cardBySlug(string $slug): ?PublicCompanyCard
    {
        $id = $this->connection->table('company_companies')->where('slug', $slug)->value('id');

        return $id === null ? null : $this->card((int) $id);
    }

    public function cards(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $cards = [];

        foreach ($this->connection->table('company_companies')
            ->whereIn('id', $companyIds)
            ->where('status', CompanyStatus::Active->value)
            ->where('is_visible', true)
            ->get(['id', 'name', 'slug', 'verified_at', 'logo_path']) as $row) {
            $cards[(int) $row->id] = new PublicCompanyCard(
                (int) $row->id,
                (string) $row->name,
                (string) $row->slug,
                $row->verified_at !== null,
                $row->logo_path === null ? null : URL::to("/api/v1/companies/{$row->slug}/logo").'?v='.CompanyImageFile::version((string) $row->logo_path),
            );
        }

        return $cards;
    }

    public function hiddenIds(): array
    {
        return array_map('intval', $this->connection->table('company_companies')
            ->where(fn ($hidden) => $hidden->where('status', '!=', CompanyStatus::Active->value)->orWhere('is_visible', false))
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }
}
