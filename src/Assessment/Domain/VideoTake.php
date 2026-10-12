<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use LogicException;

/**
 * One recording of a candidate for a video question. Its chunks arrive in order while it is `recording`; the deadline is when the answer time of the
 * question has run out plus a grace period for the upload. A `complete` take is the candidate's answer until a later take replaces it.
 */
final readonly class VideoTake
{
    /** Seconds after the answer time during which the last chunks and the completion are still accepted. */
    public const int UPLOAD_GRACE_SECONDS = 120;

    public function __construct(
        public ?int $id,
        public int $attemptId,
        public int $questionId,
        public int $number,
        public VideoTakeStatus $status,
        public ?string $mimeType,
        public ?string $path,
        public int $sizeBytes,
        public int $chunkCount,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $deadlineAt,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $rejectedAt = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}

    public static function start(int $attemptId, int $questionId, int $number, int $answerSeconds, DateTimeImmutable $now): self
    {
        return new self(null, $attemptId, $questionId, $number, VideoTakeStatus::Recording, null, null, 0, 0, $now, $now->modify('+'.($answerSeconds + self::UPLOAD_GRACE_SECONDS).' seconds'));
    }

    public function isRecording(): bool
    {
        return $this->status === VideoTakeStatus::Recording;
    }

    public function isPastDeadline(DateTimeImmutable $now): bool
    {
        return $now > $this->deadlineAt;
    }

    public function withChunk(int $bytes, string $mimeType): self
    {
        $this->assertRecording();

        return new self($this->id, $this->attemptId, $this->questionId, $this->number, $this->status, $this->mimeType ?? $mimeType, null, $this->sizeBytes + $bytes,
            $this->chunkCount + 1, $this->startedAt, $this->deadlineAt, null, null, $this->expiresAt);
    }

    public function complete(string $path, int $sizeBytes, DateTimeImmutable $now): self
    {
        $this->assertRecording();

        return new self($this->id, $this->attemptId, $this->questionId, $this->number, VideoTakeStatus::Complete, $this->mimeType, $path, $sizeBytes,
            $this->chunkCount, $this->startedAt, $this->deadlineAt, $now, null, $this->expiresAt);
    }

    public function reject(DateTimeImmutable $now): self
    {
        if ($this->status === VideoTakeStatus::Rejected) {
            throw new LogicException('The take is already rejected.');
        }

        return new self($this->id, $this->attemptId, $this->questionId, $this->number, VideoTakeStatus::Rejected, $this->mimeType, null, 0,
            $this->chunkCount, $this->startedAt, $this->deadlineAt, $this->completedAt, $now, $this->expiresAt);
    }

    /** The retention period is over: the file is gone, the take stays as the record of what was answered. */
    public function purge(): self
    {
        if ($this->status !== VideoTakeStatus::Complete) {
            throw new LogicException('Only a complete take is purged.');
        }

        return new self($this->id, $this->attemptId, $this->questionId, $this->number, VideoTakeStatus::Purged, $this->mimeType, null, $this->sizeBytes,
            $this->chunkCount, $this->startedAt, $this->deadlineAt, $this->completedAt, null, $this->expiresAt);
    }

    private function assertRecording(): void
    {
        if (! $this->isRecording()) {
            throw new LogicException('Only a take that is recording can change.');
        }
    }
}
