<?php

namespace Src\Company\Application\Queries;

use Src\Company\Domain\CompanyRepository;

final class GetAccountProgress
{
    public function __construct(private CompanyRepository $companies) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId): array
    {
        $this->companies->idForUser($userId);

        return $this->companies->accountProgress($userId);
    }
}
