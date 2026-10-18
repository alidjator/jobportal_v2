<?php

namespace Src\Messaging\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Application\MessageReader;
use Src\Messaging\Application\MessageRow;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;

/** Keyset pagination over the message id, newest first: a page never repeats or skips a message while new ones arrive. */
final readonly class DatabaseMessageReader implements MessageReader
{
    public function __construct(private ConnectionInterface $connection, private MessageRowFactory $rows) {}

    public function page(int $conversationId, CursorRequest $request): CursorPage
    {
        $query = $this->connection->table('messaging_messages')->where('conversation_id', $conversationId);

        if ($request->cursor !== null) {
            $query->where('id', '<', (int) KeysetCursor::decode($request->cursor, 1)[0]);
        }

        $found = $query->orderByDesc('id')->limit($request->perPage + 1)->get()->all();
        $hasMore = count($found) > $request->perPage;
        $found = array_slice($found, 0, $request->perPage);
        $last = $found === [] ? null : $found[array_key_last($found)];

        return new CursorPage($this->rows->make($found), $hasMore && $last !== null ? KeysetCursor::encode([(int) $last->id]) : null, $request->perPage);
    }

    public function one(int $messageId): ?MessageRow
    {
        $row = $this->connection->table('messaging_messages')->where('id', $messageId)->first();

        return $row === null ? null : $this->rows->make([$row])[0];
    }

    public function upTo(int $conversationId, ?int $upToMessageId, int $limit): array
    {
        $query = $this->connection->table('messaging_messages')->where('conversation_id', $conversationId);

        if ($upToMessageId !== null) {
            $query->where('id', '<=', $upToMessageId);
        }

        return $this->rows->make($query->orderByDesc('id')->limit($limit)->get()->all());
    }
}
