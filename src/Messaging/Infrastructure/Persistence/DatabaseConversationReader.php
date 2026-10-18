<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Messaging\Application\ConversationAudience;
use Src\Messaging\Application\ConversationReader;
use Src\Messaging\Application\ConversationRow;
use Src\Messaging\Application\MessageRow;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use stdClass;

/**
 * The conversation lists. Keyset pagination over (time of the last message, id), newest first. A conversation is unread for the caller when the other side wrote a
 * message (not deleted) after the caller's read pointer.
 */
final readonly class DatabaseConversationReader implements ConversationReader
{
    private const string SORT_TIME = 'COALESCE(c.last_message_at, c.created_at)';

    public function __construct(private ConnectionInterface $connection, private MessageRowFactory $messages) {}

    public function page(ConversationAudience $audience, CursorRequest $request): CursorPage
    {
        $query = $this->visible($audience)->select('c.*')->selectRaw($this->unreadSql().' as unread', $this->unreadBindings($audience));

        if ($request->cursor !== null) {
            [$time, $id] = KeysetCursor::decode($request->cursor, 2);
            $query->whereRaw('('.self::SORT_TIME.' < ? or ('.self::SORT_TIME.' = ? and c.id < ?))', [$time, $time, (int) $id]);
        }

        $found = $query->orderByRaw(self::SORT_TIME.' desc')->orderByDesc('c.id')->limit($request->perPage + 1)->get()->all();
        $hasMore = count($found) > $request->perPage;
        $found = array_slice($found, 0, $request->perPage);
        $last = $found === [] ? null : $found[array_key_last($found)];

        return new CursorPage(
            $this->rows($found),
            $hasMore && $last !== null ? KeysetCursor::encode([(string) ($last->last_message_at ?? $last->created_at), (int) $last->id]) : null,
            $request->perPage,
        );
    }

    public function one(ConversationAudience $audience, int $conversationId): ?ConversationRow
    {
        $row = $this->visible($audience)->where('c.id', $conversationId)->select('c.*')->selectRaw($this->unreadSql().' as unread', $this->unreadBindings($audience))->first();

        return $row === null ? null : $this->rows([$row])[0];
    }

    public function unreadCount(ConversationAudience $audience): int
    {
        return $this->visible($audience)->whereRaw($this->unreadSql(), $this->unreadBindings($audience))->count();
    }

    private function visible(ConversationAudience $audience): Builder
    {
        $query = $this->connection->table('messaging_conversations as c');

        if ($audience->side === SenderSide::Seeker) {
            return $query->where('c.job_seeker_user_id', $audience->userId);
        }

        $query->where('c.company_id', $audience->companyId);

        if ($audience->seesAllJobs) {
            return $query;
        }

        return $query->where(function (Builder $either) use ($audience): void {
            $either->where('c.started_by_user_id', $audience->userId);

            if ($audience->visibleSeekerIds !== []) {
                $either->orWhereIn('c.job_seeker_user_id', $audience->visibleSeekerIds);
            }
        });
    }

    private function unreadSql(): string
    {
        return 'exists (select 1 from messaging_messages um where um.conversation_id = c.id and um.sender_side <> ? and um.deleted_at is null '
            .'and um.id > coalesce((select r.last_read_message_id from messaging_conversation_reads r where r.conversation_id = c.id and r.user_id = ?), 0))';
    }

    /** @return list<string|int> */
    private function unreadBindings(ConversationAudience $audience): array
    {
        return [$audience->side->value, $audience->userId];
    }

    /**
     * @param  list<stdClass>  $found
     * @return list<ConversationRow>
     */
    private function rows(array $found): array
    {
        if ($found === []) {
            return [];
        }

        $latest = $this->connection->table('messaging_messages')
            ->whereIn('conversation_id', array_map(fn (stdClass $row): int => (int) $row->id, $found))
            ->groupBy('conversation_id')
            ->selectRaw('max(id) as id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        /** @var array<int, MessageRow> $lastMessages */
        $lastMessages = [];

        if ($latest !== []) {
            $messageRows = $this->connection->table('messaging_messages')->whereIn('id', $latest)->get()->all();

            foreach ($this->messages->make($messageRows) as $message) {
                $lastMessages[$message->conversationId] = $message;
            }
        }

        $utc = new DateTimeZone('UTC');

        return array_map(fn (stdClass $row): ConversationRow => new ConversationRow(
            (int) $row->id,
            (int) $row->company_id,
            (int) $row->job_seeker_user_id,
            (int) $row->started_by_user_id,
            $row->closed_at === null ? null : new DateTimeImmutable((string) $row->closed_at, $utc),
            $row->last_message_at === null ? null : new DateTimeImmutable((string) $row->last_message_at, $utc),
            (bool) $row->unread,
            $lastMessages[(int) $row->id] ?? null,
            $row->blocked_by_seeker_at === null ? null : new DateTimeImmutable((string) $row->blocked_by_seeker_at, $utc),
        ), $found);
    }
}
