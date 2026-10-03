<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\ReferenceDataRepository;

final class ListCountries
{
    public function __construct(private ReferenceDataRepository $repository) {}

    public function __invoke(): array
    {
        return $this->repository->countries();
    }
}
