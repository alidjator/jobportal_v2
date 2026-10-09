<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Domain\ReadStateRepository;
use Src\Messaging\Domain\UnreadReminder;
use stdClass;

final readonly class DatabaseReadStateRepository implements ReadStateRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function markRead(int $conversationId, int $userId, ?int $lastReadMessageId, DateTimeImmutable $at): void
    {
        $time = $this->format($at);

        $this->connection->table('messaging_conversation_reads')->insertOrIgnore([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        $changes = ['unread_since' => null, 'emailed_at' => null, 'updated_at' => $time];

        if ($lastReadMessageId !== null) {
            // The pointer only moves forward.
            $changes['last_read_message_id'] = $this->connection->raw('GREATEST(COALESCE(last_read_message_id, 0), '.$lastReadMessageId.')');
        }

        $this->connection->table('messaging_conversation_reads')->where('conversation_id', $conversationId)->where('user_id', $userId)->update($changes);
    }

    public function markUnread(int $conversationId, array $userIds, DateTimeImmutable $at): void
    {
        $time = $this->format($at);

        foreach (array_values(array_unique($userIds)) as $userId) {
            $this->connection->table('messaging_conversation_reads')->insertOrIgnore([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            $this->connection->table('messaging_conversation_reads')
                ->where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->whereNull('unread_since')
                ->update(['unread_since' => $time, 'emailed_at' => null, 'updated_at' => $time]);
        }
    }

    public function dueForReminder(DateTimeImmutable $before, int $limit): array
    {
        return $this->connection->table('messaging_conversation_reads')
            ->whereNotNull('unread_since')
            ->where('unread_since', '<=', $this->format($before))
            ->whereNull('emailed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (stdClass $row): UnreadReminder => new UnreadReminder((int) $row->id, (int) $row->conversation_id, (int) $row->user_id))
            ->all();
    }

    public function markEmailed(int $readStateId, DateTimeImmutable $at): void
    {
        $this->connection->table('messaging_conversation_reads')->where('id', $readStateId)->update(['emailed_at' => $this->format($at)]);
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
