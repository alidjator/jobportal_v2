<?php

namespace Src\Recruitment\Infrastructure\Video;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Recruitment\Application\Video\RecordingSignal;

/** Queued by Laravel, so a slow or absent Reverb server never slows or fails a recording. */
final readonly class BroadcastRecordingSignal implements RecordingSignal
{
    public function __construct(private Dispatcher $events) {}

    public function changed(int $interviewId, bool $recording): void
    {
        $this->events->dispatch(new RecordingChangedBroadcast($interviewId, $recording));
    }
}
