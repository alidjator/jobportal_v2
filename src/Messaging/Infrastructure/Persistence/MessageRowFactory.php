<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Application\AttachmentRow;
use Src\Messaging\Application\MessageRow;
use Src\Messaging\Domain\SenderSide;
use stdClass;

/** Builds message rows (with their files) from rows of `messaging_messages`, loading the files of many messages in one query. */
final readonly class MessageRowFactory
{
    public function __construct(private ConnectionInterface $connection) {}

    /**
     * @param  list<stdClass>  $rows
     * @return list<MessageRow> in the order of the given rows
     */
    public function make(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $files = [];

        $links = $this->connection->table('messaging_message_attachments')
            ->join('messaging_attachments', 'messaging_attachments.id', '=', 'messaging_message_attachments.attachment_id')
            ->whereIn('messaging_message_attachments.message_id', array_map(fn (stdClass $row): int => (int) $row->id, $rows))
            ->orderBy('messaging_message_attachments.id')
            ->get(['messaging_message_attachments.message_id', 'messaging_attachments.id', 'messaging_attachments.name', 'messaging_attachments.mime', 'messaging_attachments.size_bytes']);

        foreach ($links as $link) {
            $files[(int) $link->message_id][] = new AttachmentRow((int) $link->id, (string) $link->name, (string) $link->mime, (int) $link->size_bytes);
        }

        return array_map(fn (stdClass $row): MessageRow => new MessageRow(
            (int) $row->id,
            (int) $row->conversation_id,
            SenderSide::from((string) $row->sender_side),
            (int) $row->sender_user_id,
            $row->body === null ? null : (string) $row->body,
            $files[(int) $row->id] ?? [],
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            $row->deleted_at === null ? null : new DateTimeImmutable((string) $row->deleted_at, new DateTimeZone('UTC')),
        ), $rows);
    }
}
