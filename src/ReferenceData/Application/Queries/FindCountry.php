<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\ReferenceDataRepository;

final class FindCountry
{
    public function __construct(private ReferenceDataRepository $repository) {}

    public function __invoke(int $id): ?array
    {
        return $this->repository->country($id);
    }
}
