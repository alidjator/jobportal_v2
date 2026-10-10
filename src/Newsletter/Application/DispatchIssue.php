<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\DeliveryRepository;
use Src\Newsletter\Domain\IssueRepository;
use Src\Newsletter\Domain\IssueStatus;
use Src\Newsletter\Domain\Subscriber;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Shared\Application\Clock;

/**
 * The queued part of a send: walks the confirmed subscribers in chunks, adds a delivery for each and queues it. Running it again (a retried job) queues only
 * the deliveries that are still queued, so nobody gets the issue twice. An issue with no delivery to make is finished at once.
 */
final readonly class DispatchIssue
{
    private const int CHUNK = 500;

    public function __construct(
        private IssueRepository $issues,
        private SubscriberRepository $subscribers,
        private DeliveryRepository $deliveries,
        private NewsletterQueue $queue,
        private Clock $clock,
    ) {}

    public function execute(int $issueId): void
    {
        $issue = $this->issues->find($issueId);

        if ($issue === null || $issue->status !== IssueStatus::Sending) {
            return;
        }

        $after = 0;

        do {
            $chunk = $this->subscribers->confirmedAfter($after, self::CHUNK);

            if ($chunk === []) {
                break;
            }

            $ids = array_map(static fn (Subscriber $subscriber): int => (int) $subscriber->id, $chunk);

            foreach ($this->deliveries->queue($issueId, $ids, $this->clock->now()) as $deliveryId) {
                $this->queue->queueDelivery($deliveryId);
            }

            $after = max($ids);
        } while (count($chunk) === self::CHUNK);

        $this->issues->markDispatched($issueId, $this->clock->now());
        $this->issues->refreshCounts($issueId, $this->clock->now());
    }
}
