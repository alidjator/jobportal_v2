<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\IssueRepository;
use Src\Shared\Application\Accounts\AdminContacts;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** Sends an issue, as it will look, to the admin's own email so they can read it before the real send. It works for any issue and counts for nothing. */
final readonly class SendIssueTest
{
    /** The test email has no subscriber, so it carries a link that does nothing: the token of subscriber 0 never matches anyone. */
    private const int NO_SUBSCRIBER = 0;

    public function __construct(
        private IssueRepository $issues,
        private AdminContacts $admins,
        private IssueMessages $messages,
        private NewsletterLinks $links,
        private NewsletterMailer $mailer,
    ) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws ExternalServiceUnavailable `newsletter-unavailable`
     */
    public function execute(Actor $actor, int $issueId, string $locale): void
    {
        $adminId = NewsletterAccess::assertAdmin($actor);
        $this->links->assertAvailable();

        $issue = $this->issues->find($issueId) ?? throw EntityNotFound::of('Newsletter issue', $issueId);
        $email = $this->admins->emailOf($adminId) ?? throw EntityNotFound::of('Admin', $adminId);

        $this->mailer->send($this->messages->for($issue, self::NO_SUBSCRIBER, $email, $locale, '[Test] '));
    }
}
