<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The author lists their tests (the company's, the provider's or the library's), newest first. */
final readonly class ListTests
{
    public function __construct(private AuthoringAccess $access, private TestRepository $tests) {}

    /**
     * @return Page<Test>
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     */
    public function execute(Actor $actor, ?TestStatus $status, ?string $search, PageRequest $request): Page
    {
        return $this->tests->pageOwnedBy($this->access->author($actor)->owner, $status, $search, $request);
    }
}
