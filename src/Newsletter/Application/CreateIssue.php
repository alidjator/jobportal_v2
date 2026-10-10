<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Issue;
use Src\Newsletter\Domain\IssueRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Starts a new issue as a draft. */
final readonly class CreateIssue
{
    public function __construct(private IssueRepository $issues, private Clock $clock) {}

    /** @throws AccessDenied `admin-only` */
    public function execute(Actor $actor, string $subject, string $body): Issue
    {
        $adminId = NewsletterAccess::assertAdmin($actor);

        return $this->issues->add(Issue::draft(trim($subject), $body, $adminId, $this->clock->now()));
    }
}
