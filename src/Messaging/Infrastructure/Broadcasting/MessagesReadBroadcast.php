<?php

namespace Src\Messaging\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Tells the people watching a conversation that one side read it up to a message (`MessagesRead`). */
final class MessagesReadBroadcast implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $conversationId,
        public readonly string $side,
        public readonly int $userId,
        public readonly ?int $lastReadMessageId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'MessagesRead';
    }

    public function broadcastQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['side' => $this->side, 'user_id' => $this->userId, 'last_read_message_id' => $this->lastReadMessageId];
    }
}
