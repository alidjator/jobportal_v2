<?php

namespace Src\Recruitment\Application\Video;

/** Tells the people in a video interview that recording started or stopped, so every browser can show the indicator. Ids and the state only. */
interface RecordingSignal
{
    public function changed(int $interviewId, bool $recording): void;
}
