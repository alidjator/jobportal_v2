<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Domain\Attachment;
use Src\Messaging\Domain\AttachmentRepository;

final readonly class DatabaseAttachmentRepository implements AttachmentRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function addAll(array $attachments): array
    {
        $saved = [];

        foreach ($attachments as $attachment) {
            $id = (int) $this->connection->table('messaging_attachments')->insertGetId([
                'owner_user_id' => $attachment->ownerUserId,
                'name' => $attachment->name,
                'path' => $attachment->path,
                'mime' => $attachment->mime,
                'size_bytes' => $attachment->sizeBytes,
                'created_at' => $attachment->createdAt->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT),
            ]);

            $saved[] = new Attachment($attachment->ownerUserId, $attachment->name, $attachment->path, $attachment->mime, $attachment->sizeBytes, $attachment->createdAt, $id);
        }

        return $saved;
    }

    public function deleteUnlinked(array $attachmentIds): array
    {
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

    public function find(int $attachmentId): ?Attachment
    {
        $row = $this->connection->table('messaging_attachments')->where('id', $attachmentId)->first();

        return $row === null ? null : new Attachment(
            (int) $row->owner_user_id,
            (string) $row->name,
            (string) $row->path,
            (string) $row->mime,
            (int) $row->size_bytes,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }

    public function isLinked(int $messageId, int $attachmentId): bool
    {
        return $this->connection->table('messaging_message_attachments')->where('message_id', $messageId)->where('attachment_id', $attachmentId)->exists();
    }
}
