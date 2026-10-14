<?php

namespace Src\Company\Application\Admin;

use Src\Company\Application\Team\OwnerTransfer;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A platform admin names the owner of a company, for a company whose owner is gone. The owner must be an active company admin of that company. */
final readonly class SetCompanyOwner
{
    public function __construct(
        private CompanyRepository $companies,
        private OwnerTransfer $transfer,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company or the member does not exist
     * @throws StateConflict `owner-target-not-company-admin`
     */
    public function execute(Actor $actor, int $companyId, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can set the owner of a company.');
        }

        $company = $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);

        $this->transfer->execute($company, $userId, (int) $actor->id, $ipAddress, $userAgent);
    }
}
