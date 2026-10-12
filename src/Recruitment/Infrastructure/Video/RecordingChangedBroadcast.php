<?php

namespace Src\Recruitment\Infrastructure\Video;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Src\Shared\Infrastructure\Queue\QueueName;

/** `RecordingChanged` on `private-interview-video.{id}`: the interview id and whether it is being recorded. */
final class RecordingChangedBroadcast implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(public readonly int $interviewId, public readonly bool $recording) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('interview-video.'.$this->interviewId);
    }

    public function broadcastAs(): string
    {
        return 'RecordingChanged';
    }

    public function broadcastQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['interview_id' => $this->interviewId, 'recording' => $this->recording];
    }
}
