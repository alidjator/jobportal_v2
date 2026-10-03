<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Domain\ReferenceDataRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class FindLanguage
{
    public function __construct(private ReferenceDataRepository $repository) {}

    public function __invoke(string $code): array
    {
        return $this->repository->language($code) ?? throw new BusinessRuleViolation('language_not_found');
    }
}
