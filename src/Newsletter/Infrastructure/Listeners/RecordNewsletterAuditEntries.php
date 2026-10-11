<?php

namespace Src\Newsletter\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Newsletter\Domain\Events\NewsletterIssueSent;
use Src\Newsletter\Domain\Events\NewsletterSubscriberDeleted;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Newsletter context: an issue sent to the list (with the number of recipients) and a subscriber erased. The entries hold ids and
 * counts, never an address. Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class RecordNewsletterAuditEntries implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly AuditTrail $trail) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onNewsletterIssueSent(NewsletterIssueSent $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::NEWSLETTER_ISSUE_SENT, 'newsletter_issue', $event->issueId,
            ['recipients' => $event->recipients], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onNewsletterSubscriberDeleted(NewsletterSubscriberDeleted $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::NEWSLETTER_SUBSCRIBER_DELETED, 'newsletter_subscriber', $event->subscriberId,
            [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
