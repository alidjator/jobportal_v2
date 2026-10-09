<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;

interface MessageRepository
{
    public function findById(int $messageId): ?Message;

    /** Saves a new message and links its files. */
    public function add(Message $message): Message;

    /**
     * Deletes the message softly: its text and its links to files go. Returns the paths of the stored files that no other message uses any more (their
     * rows are deleted too), for the caller to delete from the disk.
     *
     * @return list<string>
     */
    public function delete(int $messageId, DateTimeImmutable $at): array;

    /** The account of the latest message the side wrote in the conversation that was not deleted, or null when it wrote none. */
    public function lastWriter(int $conversationId, SenderSide $side): ?int;

    /** How many messages the company's members sent since the time, deleted ones included, across every conversation of the company. */
    public function sentByCompanySince(int $companyId, DateTimeImmutable $since): int;

    public function latestId(int $conversationId): ?int;
}
