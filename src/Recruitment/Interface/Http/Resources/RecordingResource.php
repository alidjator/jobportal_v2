<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\Video\InterviewRecording;

/**
 * A recording of an interview. `status` is `recording`, `processing` (being converted and stored), `ready` (the file can be streamed) or `failed` (no file).
 * `expires_at` is when it is deleted by itself.
 *
 * @property InterviewRecording $resource
 */
final class RecordingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $recording = $this->resource;

        return [
            'id' => $recording->id,
            'interview_id' => $recording->interviewId,
            /** @var 'recording'|'processing'|'ready'|'failed' */
            'status' => $recording->status->value,
            'started_by' => $recording->startedBy,
            'started_at' => $recording->startedAt->format(DATE_ATOM),
            'stopped_at' => $recording->stoppedAt?->format(DATE_ATOM),
            'duration_seconds' => $recording->durationSeconds,
            'size_bytes' => $recording->sizeBytes,
            'expires_at' => $recording->expiresAt?->format(DATE_ATOM),
        ];
    }
}
