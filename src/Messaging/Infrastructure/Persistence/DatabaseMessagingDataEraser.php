<?php

namespace Src\Messaging\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Messaging\Domain\MessagingDataEraser;
use Src\Messaging\Domain\SenderSide;

/** Erases what a job seeker wrote: the files and the text of the messages they sent. What a company member wrote on behalf of a company stays. */
final readonly class DatabaseMessagingDataEraser implements MessagingDataEraser
{
    public function __construct(private ConnectionInterface $connection) {}

    public function attachmentPaths(int $userId): array
    {
        return $this->ownedAttachments($userId)->pluck('messaging_attachments.path')->map(fn (mixed $path): string => (string) $path)->unique()->values()->all();
    }

    public function clearPersonalData(int $userId): void
    {
        $ids = $this->ownedAttachments($userId)->pluck('messaging_attachments.id')->all();

        if ($ids !== []) {
            $this->connection->table('messaging_attachments')->whereIn('id', $ids)->delete();
        }

        $this->connection->table('messaging_messages')
            ->where('sender_user_id', $userId)
            ->where('sender_side', SenderSide::Seeker->value)
            ->whereNotNull('body')
            ->update(['body' => null]);

        $this->connection->table('messaging_conversation_reads')->where('user_id', $userId)->delete();
    }

    private function ownedAttachments(int $userId): Builder
    {
        return $this->connection->table('messaging_attachments')
            ->join('messaging_message_attachments', 'messaging_message_attachments.attachment_id', '=', 'messaging_attachments.id')
            ->join('messaging_messages', 'messaging_messages.id', '=', 'messaging_message_attachments.message_id')
            ->where('messaging_attachments.owner_user_id', $userId)
            ->where('messaging_messages.sender_side', SenderSide::Seeker->value)
            ->distinct();
    }
}
