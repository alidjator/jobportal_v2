<?php

namespace Src\Recruitment\Application\Video;

use DateTimeImmutable;

/** The lobby of an interview: `idle` (nobody asked to enter), `waiting` (the candidate waits since `$waitingSince`) or `admitted`. */
final readonly class VideoLobby
{
    public const string IDLE = 'idle';

    public function __construct(public string $state, public ?DateTimeImmutable $waitingSince = null, public ?DateTimeImmutable $admittedAt = null) {}
}
