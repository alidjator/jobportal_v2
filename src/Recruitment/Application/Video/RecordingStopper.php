<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Domain\Events\RecordingStopped;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\VideoRoom;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** Stops the running recording of a room, for a member who pressed stop and for the room being closed. Callers announce it after their transaction commits. */
final readonly class RecordingStopper
{
    public function __construct(
        private RecordingRepository $recordings,
        private VideoRoomGateway $gateway,
        private RecordingSignal $signal,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * Stops the video server's recording and marks the recording as processing; null when none was running.
     *
     * @throws ExternalServiceUnavailable
     */
    public function stop(int $interviewId, VideoRoom $room): ?InterviewRecording
    {
        $running = $this->recordings->active($interviewId, forUpdate: true);

        if (! $running instanceof InterviewRecording) {
            return null;
        }

        $this->gateway->stopRecording((int) $room->roomId, (string) $room->secret);

        $stopped = $running->stop($this->clock->now());
        $this->recordings->update($stopped);

        return $stopped;
    }

    /** Starts the conversion and tells the people in the room. */
    public function announce(InterviewRecording $stopped): void
    {
        $this->events->dispatch([new RecordingStopped((int) $stopped->id, $this->clock->now())]);
        $this->signal->changed($stopped->interviewId, false);
    }
}
