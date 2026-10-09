<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Domain\Message;
use Src\Messaging\Domain\MessageRepository;
use Src\Messaging\Domain\SenderSide;
use stdClass;

final readonly class DatabaseMessageRepository implements MessageRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function findById(int $messageId): ?Message
    {
        $row = $this->connection->table('messaging_messages')->where('id', $messageId)->first();

        if ($row === null) {
            return null;
        }

        $ids = $this->connection->table('messaging_message_attachments')->where('message_id', $messageId)->orderBy('id')->pluck('attachment_id')
            ->map(fn (mixed $id): int => (int) $id)->all();

        return $this->toMessage($row, $ids);
    }

    public function add(Message $message): Message
    {
        $id = (int) $this->connection->table('messaging_messages')->insertGetId([
            'conversation_id' => $message->conversationId,
            'sender_side' => $message->side->value,
            'sender_user_id' => $message->senderUserId,
            'body' => $message->body,
            'created_at' => $this->format($message->createdAt),
        ]);

        if ($message->attachmentIds !== []) {
            $this->connection->table('messaging_message_attachments')->insert(array_map(
                static fn (int $attachmentId): array => ['message_id' => $id, 'attachment_id' => $attachmentId],
                $message->attachmentIds,
            ));
        }

        return new Message($message->conversationId, $message->side, $message->senderUserId, $message->body, $message->attachmentIds, $message->createdAt, null, $id);
    }

    public function delete(int $messageId, DateTimeImmutable $at): array
    {
        $attachmentIds = $this->connection->table('messaging_message_attachments')->where('message_id', $messageId)->pluck('attachment_id')
            ->map(fn (mixed $id): int => (int) $id)->all();

        $this->connection->table('messaging_messages')->where('id', $messageId)->update(['body' => null, 'deleted_at' => $this->format($at)]);
        $this->connection->table('messaging_message_attachments')->where('message_id', $messageId)->delete();

        if ($attachmentIds === []) {
            return [];
        }

        $unused = $this->connection->table('messaging_attachments')
            ->whereIn('id', $attachmentIds)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('messaging_message_attachments')->whereColumn('messaging_message_attachments.attachment_id', 'messaging_attachments.id'));

        $paths = (clone $unused)->pluck('path')->map(fn (mixed $path): string => (string) $path)->all();
        $unused->delete();

        return $paths;
    }

    public function lastWriter(int $conversationId, SenderSide $side): ?int
    {
        $id = $this->connection->table('messaging_messages')
            ->where('conversation_id', $conversationId)
            ->where('sender_side', $side->value)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('sender_user_id');

        return $id === null ? null : (int) $id;
    }

    public function sentByCompanySince(int $companyId, DateTimeImmutable $since): int
    {
        return $this->connection->table('messaging_messages')
            ->join('messaging_conversations', 'messaging_conversations.id', '=', 'messaging_messages.conversation_id')
            ->where('messaging_conversations.company_id', $companyId)
            ->where('messaging_messages.sender_side', SenderSide::Company->value)
            ->where('messaging_messages.created_at', '>=', $this->format($since))
            ->count();
    }

    public function latestId(int $conversationId): ?int
    {
        $id = $this->connection->table('messaging_messages')->where('conversation_id', $conversationId)->max('id');

        return $id === null ? null : (int) $id;
    }

    /** @param  list<int>  $attachmentIds */
    private function toMessage(stdClass $row, array $attachmentIds): Message
    {
        return new Message(
            (int) $row->conversation_id,
            SenderSide::from((string) $row->sender_side),
            (int) $row->sender_user_id,
            $row->body === null ? null : (string) $row->body,
            $attachmentIds,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            $row->deleted_at === null ? null : new DateTimeImmutable((string) $row->deleted_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
