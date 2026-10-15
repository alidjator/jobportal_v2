<?php

namespace Src\Company\Application\NameChange;

use Src\Company\Domain\NameChangeRequest;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Company\Domain\NameChangeStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** An admin holding `companies.manage` lists the name change requests of every company, the pending ones first. */
final readonly class ListNameChangeRequestsForAdmin
{
    public function __construct(private NameChangeRequestRepository $requests, private CompanyDirectory $companies) {}

    /**
     * @return Page<AdminNameChange>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, ?NameChangeStatus $status, PageRequest $request): Page
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read the name change requests of every company.');
        }

        $page = $this->requests->page($status, $request);
        $summaries = $this->companies->summaries(array_values(array_unique(array_map(static fn (NameChangeRequest $r): int => $r->companyId, $page->items))));

        return new Page(
            array_map(static fn (NameChangeRequest $r): AdminNameChange => new AdminNameChange($r, $summaries[$r->companyId] ?? null), $page->items),
            $page->total,
            $page->request,
        );
    }
}
