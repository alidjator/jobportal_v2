<?php

namespace Src\Messaging\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Messaging\Application\MessagingNotificationType;
use Src\Messaging\Domain\Events\MessageReportDecided;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Tells the person who reported a conversation that the report was reviewed. The notice carries the conversation id and nothing about the outcome. Runs on the `default` queue, retried 3 times. */
final class NotifyReporterOfReview implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly InAppNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(MessageReportDecided $event): void
    {
        if ($event->reporterUserId !== null) {
            $this->notifier->send([$event->reporterUserId], MessagingNotificationType::REPORT_REVIEWED, ['conversation_id' => $event->conversationId]);
        }
    }
}
