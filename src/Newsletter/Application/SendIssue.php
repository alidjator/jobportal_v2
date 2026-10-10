<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Events\NewsletterIssueSent;
use Src\Newsletter\Domain\IssueRepository;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Starts the sending of a draft to every confirmed subscriber. The issue becomes `sending` at once (so it cannot be sent twice) and a queued job does the rest.
 * The number of recipients is the number of confirmed subscribers at this moment.
 */
final readonly class SendIssue
{
    public const string NO_RECIPIENTS = 'newsletter-no-recipients';

    public function __construct(
        private IssueRepository $issues,
        private SubscriberRepository $subscribers,
        private NewsletterLinks $links,
        private NewsletterQueue $queue,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `newsletter-issue-not-draft`, `newsletter-no-recipients`
     * @throws ExternalServiceUnavailable `newsletter-unavailable`
     */
    public function execute(Actor $actor, int $issueId, ?string $ipAddress, ?string $userAgent): void
    {
        $adminId = NewsletterAccess::assertAdmin($actor);
        $this->links->assertAvailable();
        $now = $this->clock->now();

        $recipients = $this->transactions->run(function () use ($issueId, $adminId, $now): int {
            $issue = $this->issues->find($issueId, true) ?? throw EntityNotFound::of('Newsletter issue', $issueId);

            if (! $issue->isDraft()) {
                throw new StateConflict('This issue was already sent.', UpdateIssue::NOT_DRAFT);
            }

            $count = $this->subscribers->countConfirmed();

            if ($count === 0) {
                throw new StateConflict('There is nobody to send this issue to.', self::NO_RECIPIENTS);
            }

            $this->issues->update($issue->sending($adminId, $count, $now));

            return $count;
        });

        $this->queue->dispatchIssue($issueId);
        $this->events->dispatch([new NewsletterIssueSent($issueId, $adminId, $recipients, $ipAddress, $userAgent, $now)]);
    }
}
