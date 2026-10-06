<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Support\Facades\URL;
use Src\Company\Application\Images\CompanyImageFile;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Company\PublicCompanyCard;

final readonly class RepositoryPublicCompanies implements PublicCompanies
{
    public function __construct(private CompanyRepository $companies) {}

    public function card(int $companyId): ?PublicCompanyCard
    {
        $company = $this->companies->findById($companyId);

        if ($company === null || ! $company->isPublic()) {
            return null;
        }

        $logo = $company->logoPath();

        return new PublicCompanyCard(
            $companyId,
            $company->profile()->name,
            $company->slug(),
            $company->verifiedAt() !== null,
            $logo === null ? null : URL::to("/api/v1/companies/{$company->slug()}/logo").'?v='.CompanyImageFile::version($logo),
        );
    }
}
