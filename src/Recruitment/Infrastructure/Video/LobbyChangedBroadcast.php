<?php

namespace Src\Recruitment\Infrastructure\Video;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Src\Shared\Infrastructure\Queue\QueueName;

/** `LobbyChanged` on `private-interview-video.{id}`: the interview id and the lobby state only. Whoever receives it asks the API over REST. */
final class LobbyChangedBroadcast implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(public readonly int $interviewId, public readonly string $state) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('interview-video.'.$this->interviewId);
    }

    public function broadcastAs(): string
    {
        return 'LobbyChanged';
    }

    public function broadcastQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['interview_id' => $this->interviewId, 'state' => $this->state];
    }
}
