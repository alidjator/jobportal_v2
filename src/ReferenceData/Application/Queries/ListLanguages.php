<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Application\CurrentPreferences;
use Src\ReferenceData\Domain\ReferenceDataRepository;

final class ListLanguages
{
    public function __construct(
        private ReferenceDataRepository $repository,
        private CurrentPreferences $current,
    ) {}

    public function __invoke(): array
    {
        return [
            'current_language' => $this->current->language(),
            'languages' => $this->repository->languages(),
        ];
    }
}
