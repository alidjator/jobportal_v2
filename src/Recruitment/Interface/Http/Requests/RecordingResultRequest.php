<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\Video\RecordingVolume;

/** What the converter reports about one recording: it is `ready` as `file` on the shared volume, or it `failed`. */
final class RecordingResultRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'recording_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:ready,failed'],
            'file' => ['required_if:status,ready', 'string', 'regex:'.RecordingVolume::FILE_PATTERN],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function recordingId(): int
    {
        return (int) $this->validated('recording_id');
    }

    public function ready(): bool
    {
        return $this->validated('status') === 'ready';
    }

    public function convertedFile(): string
    {
        return (string) $this->validated('file');
    }

    public function durationSeconds(): ?int
    {
        $seconds = $this->validated('duration_seconds');

        return $seconds === null ? null : (int) $seconds;
    }
}
