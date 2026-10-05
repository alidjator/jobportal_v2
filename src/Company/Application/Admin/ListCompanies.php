<?php

namespace Src\Company\Application\Admin;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The companies for admins holding `companies.manage`. */
final readonly class ListCompanies
{
    public function __construct(private AdminCompanyReader $reader) {}

    /**
     * @return Page<CompanyListItem>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, CompanyListQuery $query, PageRequest $request): Page
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can list companies.');
        }

        return $this->reader->page($query, $request);
    }
}
