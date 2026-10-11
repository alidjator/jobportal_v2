<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\CompanyBatteryRepository;
use Src\Assessment\Domain\Test;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin lists the batteries granted to a company. */
final readonly class ListCompanyBatteries
{
    public function __construct(private CompanyBatteryRepository $grants, private CompanyDirectory $companies) {}

    /**
     * @return list<Test>
     *
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $companyId): array
    {
        AdminOnly::assert($actor, 'list the batteries of a company');

        if (! $this->companies->exists($companyId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        return $this->grants->grantedTo($companyId);
    }
}
