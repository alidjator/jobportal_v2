<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Issue;
use Src\Newsletter\Domain\IssueRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetIssue
{
    public function __construct(private IssueRepository $issues) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $issueId): Issue
    {
        NewsletterAccess::assertAdmin($actor);

        return $this->issues->find($issueId) ?? throw EntityNotFound::of('Newsletter issue', $issueId);
    }
}
