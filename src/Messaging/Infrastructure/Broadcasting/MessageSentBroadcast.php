<?php

namespace Src\Messaging\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Tells the people watching a conversation about a new message (`MessageSent` on `private-conversations.{id}`). The files are described, never carried. */
final class MessageSentBroadcast implements ShouldBroadcast
{
    use InteractsWithSockets;

    /** @param  array<string, mixed>  $message */
    public function __construct(public readonly int $conversationId, public readonly array $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
