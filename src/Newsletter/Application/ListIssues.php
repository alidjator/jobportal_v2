<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Issue;
use Src\Newsletter\Domain\IssueRepository;
use Src\Newsletter\Domain\IssueStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The issues, newest first. */
final readonly class ListIssues
{
    public function __construct(private IssueRepository $issues) {}

    /**
     * @return Page<Issue>
     *
     * @throws AccessDenied `admin-only`
     */
    public function execute(Actor $actor, ?IssueStatus $status, PageRequest $request): Page
    {
        NewsletterAccess::assertAdmin($actor);

        return $this->issues->page($status, $request);
    }
}
