<?php

namespace Src\Recruitment\Application\Video;

use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** The converter next to the video server. It answers at once, converts in the background and reports the result to the internal webhook. */
interface RecordingConverter
{
    /**
     * Asks to convert the raw tracks the video server wrote to the directory into one file.
     *
     * @throws ExternalServiceUnavailable
     */
    public function request(int $recordingId, string $directory): void;
}
