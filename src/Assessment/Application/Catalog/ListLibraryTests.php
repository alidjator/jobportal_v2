<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A company member lists the published tests of the system library. */
final readonly class ListLibraryTests
{
    public function __construct(private CatalogAccess $access, private TestRepository $tests) {}

    /**
     * @return Page<Test>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, ?string $search, PageRequest $request): Page
    {
        $this->access->companyId($actor);

        return $this->tests->pageLibrary($search, $request);
    }
}
