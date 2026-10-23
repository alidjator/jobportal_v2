<?php

namespace Src\Company\Application\Messages;

use Src\Company\Domain\MessageDefaultsRepository;
use Src\Shared\Application\Company\CompanyMessageTexts;

final readonly class RepositoryCompanyMessageTexts implements CompanyMessageTexts
{
    public function __construct(private MessageDefaultsRepository $defaults) {}

    public function applied(int $companyId): ?string
    {
        return $this->defaults->find($companyId)->applied;
    }

    public function hired(int $companyId): ?string
    {
        return $this->defaults->find($companyId)->hired;
    }

    public function assessment(int $companyId): ?string
    {
        return $this->defaults->find($companyId)->assessment;
    }
}
