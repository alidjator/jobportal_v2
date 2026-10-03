<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\LookupType;
use Src\ReferenceData\Domain\ReferenceDataRepository;

final class ListLookups
{
    public function __construct(private ReferenceDataRepository $repository) {}

    /** @return list<array{id:int,name:?string}> */
    public function __invoke(LookupType $type): array
    {
        return $this->repository->lookups($type);
    }
}
