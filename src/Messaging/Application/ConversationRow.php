<?php

namespace Src\Messaging\Application;

use DateTimeImmutable;

/** A conversation as stored, with the caller's unread flag and the latest message. */
final readonly class ConversationRow
{
    public function __construct(
        public int $id,
        public int $companyId,
        public int $jobSeekerUserId,
        public int $startedByUserId,
        public ?DateTimeImmutable $closedAt,
        public ?DateTimeImmutable $lastMessageAt,
        public bool $unread,
        public ?MessageRow $lastMessage,
    ) {}
}
