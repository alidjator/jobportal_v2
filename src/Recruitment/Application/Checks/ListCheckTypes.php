<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Domain\CheckType;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check types of the company, by name, the deactivated ones included. */
final readonly class ListCheckTypes
{
    public function __construct(private ChecksAccess $access, private CheckTypeRepository $types) {}

    /**
     * @return list<CheckType>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     */
    public function execute(Actor $actor): array
    {
        return $this->types->ofCompany($this->access->member($actor)->companyId);
    }
}
