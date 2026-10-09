<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetConversation
{
    public function __construct(private ConversationAccess $access, private ConversationReader $reader, private MessagingViews $views) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $conversationId): ConversationView
    {
        $participant = $this->access->participant($actor);
        $this->access->open($participant, $conversationId);

        return $this->views->conversation(
            $this->reader->one($this->access->audience($participant), $conversationId) ?? throw EntityNotFound::of('Conversation', $conversationId),
            $participant->side,
        );
    }
}
