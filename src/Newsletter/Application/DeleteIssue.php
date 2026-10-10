<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\IssueRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Deletes a draft. An issue that was sent stays as the record of what went out. */
final readonly class DeleteIssue
{
    public function __construct(private IssueRepository $issues, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `newsletter-issue-not-draft`
     */
    public function execute(Actor $actor, int $issueId): void
    {
        NewsletterAccess::assertAdmin($actor);

        $this->transactions->run(function () use ($issueId): void {
            $issue = $this->issues->find($issueId, true) ?? throw EntityNotFound::of('Newsletter issue', $issueId);

            if (! $issue->isDraft()) {
                throw new StateConflict('Only a draft can be deleted.', UpdateIssue::NOT_DRAFT);
            }

            $this->issues->delete($issueId);
        });
    }
}
