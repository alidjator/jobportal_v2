<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A company member lists the psychological batteries an admin granted to the company; nothing while the company may not use batteries. */
final readonly class ListBatteryCatalog
{
    public function __construct(private CatalogAccess $access, private TestRepository $tests) {}

    /**
     * @return Page<Test>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `psychological-disabled`
     */
    public function execute(Actor $actor, ?string $search, PageRequest $request): Page
    {
        $companyId = $this->access->companyId($actor);

        if (! $this->access->psychologicalEnabled($companyId)) {
            throw new AccessDenied('Psychological tests are not enabled for this company.', 'psychological-disabled');
        }

        return $this->tests->pageGrantedTo($companyId, $search, $request);
    }
}
