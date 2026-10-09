<?php

namespace Src\Messaging\Infrastructure\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Messaging\Application\MessagePayload;
use Src\Messaging\Application\MessageReader;
use Src\Messaging\Application\MessagingViews;
use Src\Messaging\Domain\Events\MessageDeleted;
use Src\Messaging\Domain\Events\MessageSent;
use Src\Messaging\Domain\Events\MessagesRead;
use Src\Messaging\Infrastructure\Broadcasting\MessageDeletedBroadcast;
use Src\Messaging\Infrastructure\Broadcasting\MessageSentBroadcast;
use Src\Messaging\Infrastructure\Broadcasting\MessagesReadBroadcast;

/**
 * Turns the domain events into real-time events. It runs in the request and only builds the payload; the broadcast itself is queued by Laravel (`default`), so a
 * slow or absent Reverb server never slows or fails a send.
 */
final readonly class BroadcastMessagingEvents
{
    public function __construct(private Dispatcher $events, private MessageReader $messages, private MessagingViews $views) {}

    public function sent(MessageSent $event): void
    {
        $row = $this->messages->one($event->messageId);

        if ($row !== null) {
            $this->events->dispatch(new MessageSentBroadcast($event->conversationId, MessagePayload::of($this->views->message($row))));
        }
    }

    public function deleted(MessageDeleted $event): void
    {
        $this->events->dispatch(new MessageDeletedBroadcast($event->conversationId, $event->messageId));
    }

    public function read(MessagesRead $event): void
    {
        $this->events->dispatch(new MessagesReadBroadcast($event->conversationId, $event->side->value, $event->userId, $event->lastReadMessageId));
    }
}
