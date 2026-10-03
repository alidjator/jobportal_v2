<?php

namespace Src\Billing\Application\Queries;

use Src\Billing\Domain\BillingRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class GetCompanyPlan
{
    public const TRANSACTIONS_PER_PAGE = 8;

    public function __construct(private BillingRepository $repository) {}

    /** @return array{user_plan: array<string,mixed>, transactions: array<string,mixed>} */
    public function __invoke(int $userId): array
    {
        $companyId = $this->repository->companyIdOfUser($userId) ?? throw EntityNotFound::of('Company');
        $userPlan = $this->repository->userPlanOfCompany($companyId) ?? throw EntityNotFound::of('UserPlan');

        return [
            'user_plan' => $userPlan,
            'transactions' => $this->repository->transactionsOfCompany($companyId, self::TRANSACTIONS_PER_PAGE),
        ];
    }
}
