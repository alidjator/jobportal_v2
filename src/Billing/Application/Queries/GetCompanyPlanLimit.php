<?php

namespace Src\Billing\Application\Queries;

use Src\Billing\Domain\BillingRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class GetCompanyPlanLimit
{
    public function __construct(private BillingRepository $repository) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId): array
    {
        $companyId = $this->repository->companyIdOfUser($userId) ?? throw EntityNotFound::of('Company');

        return $this->repository->userPlanOfCompany($companyId) ?? throw EntityNotFound::of('UserPlan');
    }
}
