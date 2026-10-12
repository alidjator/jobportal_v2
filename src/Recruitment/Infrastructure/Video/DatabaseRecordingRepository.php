<?php

namespace Src\Recruitment\Infrastructure\Video;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingMedia;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\RecordingStatus;
use stdClass;

final readonly class DatabaseRecordingRepository implements RecordingRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string TABLE = 'recruitment_interview_recordings';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(InterviewRecording $recording): InterviewRecording
    {
        $id = (int) $this->connection->table(self::TABLE)->insertGetId([
            'interview_id' => $recording->interviewId,
            'media' => $recording->media->value,
            'status' => $recording->status->value,
            'started_by' => $recording->startedBy,
            'started_at' => $this->format($recording->startedAt),
            'created_at' => $this->format($recording->startedAt),
            'updated_at' => $this->format($recording->startedAt),
        ]);

        return new InterviewRecording($recording->interviewId, $recording->status, $recording->startedBy, $recording->startedAt, null, null, null, null, null, null, $id, $recording->media);
    }

    public function find(int $id, bool $forUpdate = false): ?InterviewRecording
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toRecording($row);
    }

    public function update(InterviewRecording $recording): void
    {
        $this->connection->table(self::TABLE)->where('id', $recording->id)->update([
            'status' => $recording->status->value,
            'stopped_at' => $recording->stoppedAt === null ? null : $this->format($recording->stoppedAt),
            'source_file' => $recording->sourceFile,
            'path' => $recording->path,
            'size_bytes' => $recording->sizeBytes,
            'duration_seconds' => $recording->durationSeconds,
            'expires_at' => $recording->expiresAt === null ? null : $this->format($recording->expiresAt),
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    public function active(int $interviewId, bool $forUpdate = false, ?RecordingMedia $media = null): ?InterviewRecording
    {
        $query = $this->connection->table(self::TABLE)->where('interview_id', $interviewId)->where('status', RecordingStatus::Recording->value);

        if ($media instanceof RecordingMedia) {
            $query->where('media', $media->value);
        }

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toRecording($row);
    }

    public function running(RecordingMedia $media): array
    {
        return $this->connection->table(self::TABLE)
            ->where('status', RecordingStatus::Recording->value)
            ->where('media', $media->value)
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): InterviewRecording => $this->toRecording($row))
            ->all();
    }

    public function exists(int $interviewId): bool
    {
        return $this->connection->table(self::TABLE)->where('interview_id', $interviewId)->exists();
    }

    public function forInterview(int $interviewId): array
    {
        return $this->connection->table(self::TABLE)
            ->where('interview_id', $interviewId)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (stdClass $row): InterviewRecording => $this->toRecording($row))
            ->all();
    }

    public function delete(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function expired(DateTimeImmutable $now): array
    {
        return $this->connection->table(self::TABLE)
            ->where('status', RecordingStatus::Ready->value)
            ->where('expires_at', '<', $this->format($now))
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): InterviewRecording => $this->toRecording($row))
            ->all();
    }

    public function stuck(DateTimeImmutable $since): array
    {
        return $this->connection->table(self::TABLE)
            ->where('status', RecordingStatus::Processing->value)
            ->where('stopped_at', '<', $this->format($since))
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): InterviewRecording => $this->toRecording($row))
            ->all();
    }

    private function toRecording(stdClass $row): InterviewRecording
    {
        return new InterviewRecording(
            (int) $row->interview_id,
            RecordingStatus::from((string) $row->status),
            (int) $row->started_by,
            $this->time($row->started_at) ?? new DateTimeImmutable,
            $this->time($row->stopped_at),
            $row->source_file === null ? null : (string) $row->source_file,
            $row->path === null ? null : (string) $row->path,
            $row->size_bytes === null ? null : (int) $row->size_bytes,
            $row->duration_seconds === null ? null : (int) $row->duration_seconds,
            $this->time($row->expires_at),
            (int) $row->id,
            RecordingMedia::from((string) $row->media),
        );
    }

    private function time(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
