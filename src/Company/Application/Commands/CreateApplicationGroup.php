<?php

namespace Src\Company\Application\Commands;

use Src\Company\Domain\ApplicationGroupRepository;
use Src\Company\Domain\CompanyRepository;

final class CreateApplicationGroup
{
    public function __construct(private CompanyRepository $companies, private ApplicationGroupRepository $groups) {}

    /** @return array{group:array<string,mixed>,message:string} */
    public function __invoke(int $userId, string $name): array
    {
        $group = $this->groups->create($this->companies->idForUser($userId), $name);

        return ['group' => $group, 'message' => 'Group Created Successfully'];
    }
}
