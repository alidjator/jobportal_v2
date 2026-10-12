<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\VideoTake;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Assessment\Domain\VideoTakeStatus;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseVideoTakeRepository implements VideoTakeRepository
{
    private const string TABLE = 'assessment_video_takes';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id, bool $forUpdate = false): ?VideoTake
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function add(VideoTake $take): VideoTake
    {
        $now = $this->clock->now();
        $id = $this->connection->table(self::TABLE)->insertGetId($this->values($take) + [
            'attempt_id' => $take->attemptId, 'question_id' => $take->questionId, 'number' => $take->number, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find((int) $id) ?? $take;
    }

    public function update(VideoTake $take): void
    {
        $this->connection->table(self::TABLE)->where('id', $take->id)->update($this->values($take) + ['updated_at' => $this->clock->now()]);
    }

    public function scheduleExpiry(int $attemptId, DateTimeImmutable $expiresAt): void
    {
        $this->connection->table(self::TABLE)->where('attempt_id', $attemptId)->where('status', VideoTakeStatus::Complete->value)->update(['expires_at' => $expiresAt, 'updated_at' => $this->clock->now()]);
    }

    public function expired(DateTimeImmutable $now, int $limit): array
    {
        return $this->hydrateAll($this->connection->table(self::TABLE)->where('status', VideoTakeStatus::Complete->value)->where('expires_at', '<=', $now)->orderBy('id')->limit($limit)->get()->all());
    }

    public function stale(DateTimeImmutable $startedBefore, int $limit): array
    {
        return $this->hydrateAll($this->connection->table(self::TABLE)->where('status', VideoTakeStatus::Recording->value)->where('started_at', '<', $startedBefore)->orderBy('id')->limit($limit)->get()->all());
    }

    public function ofQuestion(int $attemptId, int $questionId): array
    {
        return array_values(array_map(fn (stdClass $row): VideoTake => $this->hydrate($row), $this->connection->table(self::TABLE)
            ->where('attempt_id', $attemptId)->where('question_id', $questionId)->orderBy('number')->get()->all()));
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<VideoTake>
     */
    private function hydrateAll(array $rows): array
    {
        return array_map(fn (stdClass $row): VideoTake => $this->hydrate($row), $rows);
    }

    /** @return array<string, mixed> */
    private function values(VideoTake $take): array
    {
        return [
            'status' => $take->status->value,
            'mime_type' => $take->mimeType,
            'path' => $take->path,
            'size_bytes' => $take->sizeBytes,
            'chunk_count' => $take->chunkCount,
            'started_at' => $take->startedAt,
            'deadline_at' => $take->deadlineAt,
            'completed_at' => $take->completedAt,
            'rejected_at' => $take->rejectedAt,
            'expires_at' => $take->expiresAt,
        ];
    }

    private function hydrate(stdClass $row): VideoTake
    {
        $date = static fn (?string $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable($value);

        return new VideoTake(
            (int) $row->id, (int) $row->attempt_id, (int) $row->question_id, (int) $row->number, VideoTakeStatus::from((string) $row->status),
            $row->mime_type, $row->path, (int) $row->size_bytes, (int) $row->chunk_count,
            new DateTimeImmutable((string) $row->started_at), new DateTimeImmutable((string) $row->deadline_at),
            $date($row->completed_at), $date($row->rejected_at), $date($row->expires_at),
        );
    }
}
