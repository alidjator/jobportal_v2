<?php

namespace Src\Recruitment\Infrastructure;

use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Recruitment\CompanyStage;
use Src\Shared\Application\Recruitment\CompanyStages;

final readonly class RepositoryCompanyStages implements CompanyStages
{
    public function __construct(private StageRepository $stages) {}

    public function ofCompany(int $companyId): array
    {
        $found = [];

        foreach ($this->stages->forCompany($companyId) as $stage) {
            if ($stage->id !== null) {
                $found[$stage->id] = new CompanyStage($stage->id, $stage->name, $stage->type->value);
            }
        }

        return $found;
    }
}
