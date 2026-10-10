<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\DeliveryRepository;
use Src\Newsletter\Domain\DeliveryStatus;
use Src\Newsletter\Domain\IssueRepository;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Newsletter\Domain\SubscriberStatus;
use Src\Shared\Application\Clock;

/**
 * Sends one delivery. Nothing happens when it is no longer queued (a duplicate job) or when the subscriber left or was deleted since the issue was dispatched: that
 * delivery fails without a mail. The mailer throws when the mail cannot be sent, and the job retries; after its last try {@see failed()} marks the delivery.
 */
final readonly class DeliverNewsletter
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private IssueRepository $issues,
        private SubscriberRepository $subscribers,
        private IssueMessages $messages,
        private NewsletterMailer $mailer,
        private Clock $clock,
    ) {}

    public function execute(int $deliveryId): void
    {
        $delivery = $this->deliveries->find($deliveryId);

        if ($delivery === null || $delivery->status !== DeliveryStatus::Queued) {
            return;
        }

        $issue = $this->issues->find($delivery->issueId);
        $subscriber = $this->subscribers->find($delivery->subscriberId);

        if ($issue === null || $subscriber === null || $subscriber->status !== SubscriberStatus::Confirmed) {
            $this->failed($deliveryId);

            return;
        }

        $this->mailer->send($this->messages->for($issue, (int) $subscriber->id, $subscriber->email, $subscriber->locale));

        $now = $this->clock->now();
        $this->deliveries->markSent($deliveryId, $now);
        $this->issues->refreshCounts($delivery->issueId, $now);
    }

    /** Marks a delivery that could not be made (called after the last try, and for a subscriber who left). */
    public function failed(int $deliveryId): void
    {
        $delivery = $this->deliveries->find($deliveryId);

        if ($delivery === null || $delivery->status !== DeliveryStatus::Queued) {
            return;
        }

        $now = $this->clock->now();
        $this->deliveries->markFailed($deliveryId, $now);
        $this->issues->refreshCounts($delivery->issueId, $now);
    }
}
