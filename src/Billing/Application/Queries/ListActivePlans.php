<?php

namespace Src\Billing\Application\Queries;

use Src\Billing\Domain\BillingRepository;

final class ListActivePlans
{
    public function __construct(private BillingRepository $repository) {}

    public function __invoke(): array
    {
        return $this->repository->activePlans();
    }
}
