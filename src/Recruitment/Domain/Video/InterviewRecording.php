<?php

namespace Src\Recruitment\Domain\Video;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * One recording of a video interview: started by a team member, stopped by one (or by the room closing), converted on the video server, then moved to the
 * private disk and kept until `$expiresAt`. A recording that cannot be finished is `failed` and has no file.
 */
final readonly class InterviewRecording
{
    public function __construct(
        public int $interviewId,
        public RecordingStatus $status,
        public int $startedBy,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $stoppedAt = null,
        public ?string $sourceFile = null,
        public ?string $path = null,
        public ?int $sizeBytes = null,
        public ?int $durationSeconds = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?int $id = null,
    ) {}

    public static function start(int $interviewId, int $startedBy, DateTimeImmutable $at): self
    {
        return new self($interviewId, RecordingStatus::Recording, $startedBy, $at);
    }

    public function isRecording(): bool
    {
        return $this->status === RecordingStatus::Recording;
    }

    /** @throws StateConflict `no-active-recording` */
    public function stop(DateTimeImmutable $at): self
    {
        if (! $this->isRecording()) {
            throw new StateConflict('No recording is running.', 'no-active-recording');
        }

        return $this->changed(status: RecordingStatus::Processing, stoppedAt: $at);
    }

    /** The video server finished converting: the file sits on the shared volume until it is stored. */
    public function converted(string $sourceFile, ?int $durationSeconds): self
    {
        return $this->status === RecordingStatus::Processing ? $this->changed(sourceFile: $sourceFile, durationSeconds: $durationSeconds) : $this;
    }

    public function stored(string $path, int $sizeBytes, DateTimeImmutable $expiresAt): self
    {
        return new self($this->interviewId, RecordingStatus::Ready, $this->startedBy, $this->startedAt, $this->stoppedAt, null, $path, $sizeBytes, $this->durationSeconds, $expiresAt, $this->id);
    }

    public function failed(): self
    {
        return new self($this->interviewId, RecordingStatus::Failed, $this->startedBy, $this->startedAt, $this->stoppedAt, null, null, null, $this->durationSeconds, null, $this->id);
    }

    private function changed(?RecordingStatus $status = null, ?DateTimeImmutable $stoppedAt = null, ?string $sourceFile = null, ?int $durationSeconds = null): self
    {
        return new self(
            $this->interviewId,
            $status ?? $this->status,
            $this->startedBy,
            $this->startedAt,
            $stoppedAt ?? $this->stoppedAt,
            $sourceFile ?? $this->sourceFile,
            $this->path,
            $this->sizeBytes,
            $durationSeconds ?? $this->durationSeconds,
            $this->expiresAt,
            $this->id,
        );
    }
}
