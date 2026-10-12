<?php

namespace Src\Recruitment\Infrastructure\Video;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Recruitment\Application\Video\LobbySignal;

/** Queued by Laravel, so a slow or absent Reverb server never slows or fails a join. */
final readonly class BroadcastLobbySignal implements LobbySignal
{
    public function __construct(private Dispatcher $events) {}

    public function changed(int $interviewId, string $state): void
    {
        $this->events->dispatch(new LobbyChangedBroadcast($interviewId, $state));
    }
}
