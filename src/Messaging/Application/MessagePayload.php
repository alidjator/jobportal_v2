<?php

namespace Src\Messaging\Application;

/** The JSON shape of a message, shared by the HTTP answers and the real-time events so a client reads both the same way. Files are described, never sent. */
final class MessagePayload
{
    /** @return array<string, mixed> */
    public static function of(MessageView $view): array
    {
        $message = $view->message;

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversationId,
            'sender' => ['side' => $message->side->value, 'user_id' => $message->senderUserId, 'name' => $view->senderName],
            'body' => $message->body,
            'deleted' => $message->deletedAt !== null,
            /** @var list<array{id: int, name: string, mime: string, size_bytes: int}> */
            'attachments' => array_map(fn (AttachmentRow $file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'mime' => $file->mime,
                'size_bytes' => $file->sizeBytes,
            ], $message->attachments),
            'created_at' => $message->createdAt->format(DATE_ATOM),
        ];
    }
}
