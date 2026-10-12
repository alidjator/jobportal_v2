<?php

namespace Src\Recruitment\Application\Video;

/** Tells the people of an interview that its lobby changed. The signal carries ids and the state only; whoever receives it asks over REST. */
interface LobbySignal
{
    public const string WAITING = 'waiting';

    public const string ADMITTED = 'admitted';

    public function changed(int $interviewId, string $state): void;
}
