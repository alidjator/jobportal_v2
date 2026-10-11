<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** An admin lists the published batteries of the active providers, to pick the ones to grant to a company. */
final readonly class ListPublishedBatteries
{
    public function __construct(private TestRepository $tests) {}

    /**
     * @return Page<Test>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, ?string $search, PageRequest $request): Page
    {
        AdminOnly::assert($actor, 'list batteries');

        return $this->tests->pagePublishedBatteries($search, $request);
    }
}
