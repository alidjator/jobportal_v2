<?php

namespace Src\Messaging\Application;

use DateTimeImmutable;
use Src\Messaging\Domain\SenderSide;

/** A message as stored: no names yet (they come from the accounts). A deleted message has no body and no files. */
final readonly class MessageRow
{
    /** @param  list<AttachmentRow>  $attachments */
    public function __construct(
        public int $id,
        public int $conversationId,
        public SenderSide $side,
        public int $senderUserId,
        public ?string $body,
        public array $attachments,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt,
    ) {}
}
