<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\ReferenceDataRepository;

final class ListPopularTags
{
    private const LIMIT = 10;

    public function __construct(private ReferenceDataRepository $repository) {}

    public function __invoke(): array
    {
        return $this->repository->popularTags(self::LIMIT);
    }
}
