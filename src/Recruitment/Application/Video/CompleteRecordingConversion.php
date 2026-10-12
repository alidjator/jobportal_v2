<?php

namespace Src\Recruitment\Application\Video;

/** The converter reports the result of one recording to the internal webhook. */
final readonly class CompleteRecordingConversion
{
    public function __construct(private RecordingPipeline $pipeline) {}

    public function succeeded(int $recordingId, string $file, ?int $durationSeconds): void
    {
        $this->pipeline->converted($recordingId, $file, $durationSeconds);
    }

    public function failed(int $recordingId): void
    {
        $this->pipeline->fail($recordingId);
    }
}
