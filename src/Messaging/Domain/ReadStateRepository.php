<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;

/** Who has read how far in a conversation, and when the reminder email of an unread stretch is due. */
interface ReadStateRepository
{
    /** Moves the user's read pointer to the message and clears their unread stretch (and with it the reminder, so a later unread message can be reminded again). */
    public function markRead(int $conversationId, int $userId, ?int $lastReadMessageId, DateTimeImmutable $at): void;

    /** Starts an unread stretch for each user who has none (one that has keeps its start, so the reminder counts from the first unread message). @param  list<int>  $userIds */
    public function markUnread(int $conversationId, array $userIds, DateTimeImmutable $at): void;

    /**
     * The unread stretches that began at or before the time and were not reminded yet.
     *
     * @return list<UnreadReminder>
     */
    public function dueForReminder(DateTimeImmutable $before, int $limit): array;

    public function markEmailed(int $readStateId, DateTimeImmutable $at): void;
}
