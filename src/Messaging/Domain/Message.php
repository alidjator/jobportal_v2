<?php

namespace Src\Messaging\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

/** One message of a conversation: a text, files, or both. Deleting is soft: the row stays so the thread keeps its order, but its text and files go. */
final readonly class Message
{
    /** @param  list<int>  $attachmentIds */
    public function __construct(
        public int $conversationId,
        public SenderSide $side,
        public int $senderUserId,
        public ?string $body,
        public array $attachmentIds,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt = null,
        public ?int $id = null,
    ) {}

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @throws AccessDenied `not-your-message`
     * @throws StateConflict `delete-window-over`
     */
    public function assertCanBeDeletedBy(int $userId, DateTimeImmutable $now): void
    {
        if ($this->senderUserId !== $userId) {
            throw new AccessDenied('Only the sender can delete a message.', 'not-your-message');
        }

        if ($now > $this->createdAt->modify('+'.MessagingRules::DELETE_WINDOW_MINUTES.' minutes')) {
            throw new StateConflict('A message can only be deleted within '.MessagingRules::DELETE_WINDOW_MINUTES.' minutes of sending it.', 'delete-window-over');
        }
    }
}
