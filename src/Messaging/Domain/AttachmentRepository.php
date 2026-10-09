<?php

namespace Src\Messaging\Domain;

interface AttachmentRepository
{
    /** Saves the file rows and returns them with their ids. @param  list<Attachment>  $attachments @return list<Attachment> */
    public function addAll(array $attachments): array;

    /**
     * Deletes the rows among these that no message uses and returns their paths. Used to clean up after a send that failed or was skipped.
     *
     * @param  list<int>  $attachmentIds
     * @return list<string>
     */
    public function deleteUnlinked(array $attachmentIds): array;

    public function find(int $attachmentId): ?Attachment;

    /** Whether the message holds the file. */
    public function isLinked(int $messageId, int $attachmentId): bool;
}
