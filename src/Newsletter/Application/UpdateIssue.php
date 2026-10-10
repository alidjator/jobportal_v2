<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Issue;
use Src\Newsletter\Domain\IssueRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Changes the subject and the body of a draft. An issue that is sending or sent is fixed. */
final readonly class UpdateIssue
{
    public const string NOT_DRAFT = 'newsletter-issue-not-draft';

    public function __construct(private IssueRepository $issues, private TransactionManager $transactions, private Clock $clock) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `newsletter-issue-not-draft`
     */
    public function execute(Actor $actor, int $issueId, string $subject, string $body): Issue
    {
        NewsletterAccess::assertAdmin($actor);

        return $this->transactions->run(function () use ($issueId, $subject, $body): Issue {
            $issue = $this->issues->find($issueId, true) ?? throw EntityNotFound::of('Newsletter issue', $issueId);

            if (! $issue->isDraft()) {
                throw new StateConflict('Only a draft can be changed.', self::NOT_DRAFT);
            }

            $edited = $issue->edited(trim($subject), $body, $this->clock->now());
            $this->issues->update($edited);

            return $edited;
        });
    }
}
