<?php

namespace Src\Review\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Review\Domain\ModerationReason;
use Src\Review\Domain\Reply;
use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReplyStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseReplyRepository implements ReplyRepository
{
    private const string TABLE = 'review_replies';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function save(Reply $reply): Reply
    {
        $columns = [
            'body' => $reply->body,
            'status' => $reply->status->value,
            'reason' => $reply->reason?->value,
            'note' => $reply->note,
            'written_by_user_id' => $reply->writtenByUserId,
            'moderated_by_admin_id' => $reply->moderatedByAdminId,
            'moderated_at' => $reply->moderatedAt?->format(self::TIME_FORMAT),
            'published_at' => $reply->publishedAt?->format(self::TIME_FORMAT),
            'updated_at' => $reply->updatedAt->format(self::TIME_FORMAT),
        ];

        if ($reply->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $reply->id)->update($columns);

            return $reply;
        }

        $id = (int) $this->connection->table(self::TABLE)->insertGetId($columns + ['review_id' => $reply->reviewId, 'created_at' => $reply->createdAt->format(self::TIME_FORMAT)]);

        return new Reply(
            $reply->reviewId, $reply->body, $reply->status, $reply->reason, $reply->note, $reply->writtenByUserId, $reply->moderatedByAdminId,
            $reply->moderatedAt, $reply->publishedAt, $reply->createdAt, $reply->updatedAt, $id,
        );
    }

    public function delete(int $reviewId): void
    {
        $this->connection->table(self::TABLE)->where('review_id', $reviewId)->delete();
    }

    public function find(int $reviewId, bool $forUpdate = false): ?Reply
    {
        $query = $this->connection->table(self::TABLE)->where('review_id', $reviewId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toReply($row);
    }

    public function forReviews(array $reviewIds): array
    {
        if ($reviewIds === []) {
            return [];
        }

        $replies = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('review_id', $reviewIds)->get() as $row) {
            $replies[(int) $row->review_id] = $this->toReply($row);
        }

        return $replies;
    }

    public function page(?ReplyStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        $total = $builder->count();
        $rows = $builder->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Reply => $this->toReply($row), $rows->all()), $total, $request);
    }

    private function toReply(stdClass $row): Reply
    {
        $time = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new Reply(
            (int) $row->review_id,
            (string) $row->body,
            ReplyStatus::from((string) $row->status),
            $row->reason === null ? null : ModerationReason::from((string) $row->reason),
            $row->note === null ? null : (string) $row->note,
            (int) $row->written_by_user_id,
            $row->moderated_by_admin_id === null ? null : (int) $row->moderated_by_admin_id,
            $time($row->moderated_at),
            $time($row->published_at),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
