<?php

namespace Src\Messaging\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Tells the people watching a conversation that a message was deleted (`MessageDeleted`). */
final class MessageDeletedBroadcast implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(public readonly int $conversationId, public readonly int $messageId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'MessageDeleted';
    }

    public function broadcastQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['message_id' => $this->messageId];
    }
}
