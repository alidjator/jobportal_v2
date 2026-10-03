<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\ReferenceDataRepository;

final class FindCurrency
{
    public function __construct(private ReferenceDataRepository $repository) {}

    public function __invoke(string $code): ?array
    {
        return $this->repository->currency($code);
    }
}
