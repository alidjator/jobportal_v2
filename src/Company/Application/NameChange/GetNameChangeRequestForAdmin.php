<?php

namespace Src\Company\Application\NameChange;

use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `companies.manage` reads one name change request. */
final readonly class GetNameChangeRequestForAdmin
{
    public function __construct(private NameChangeRequestRepository $requests, private CompanyDirectory $companies) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the request does not exist
     */
    public function execute(Actor $actor, int $requestId): AdminNameChange
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read the name change requests of every company.');
        }

        $request = $this->requests->find($requestId) ?? throw EntityNotFound::of('Name change request', $requestId);

        return new AdminNameChange($request, $this->companies->summaries([$request->companyId])[$request->companyId] ?? null);
    }
}
