<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\Events\MessagesRead;
use Src\Messaging\Domain\MessageRepository;
use Src\Messaging\Domain\ReadStateRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The caller has read the conversation up to its newest message. Their reminder email, if one was waiting, is cancelled with it. */
final readonly class MarkConversationRead
{
    public function __construct(
        private ConversationAccess $access,
        private MessageRepository $messages,
        private ReadStateRepository $reads,
        private Clock $clock,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $conversationId): void
    {
        $participant = $this->access->participant($actor);
        $this->access->open($participant, $conversationId);

        $now = $this->clock->now();
        $latest = $this->messages->latestId($conversationId);
        $this->reads->markRead($conversationId, $participant->userId, $latest, $now);

        $this->events->dispatch([new MessagesRead($conversationId, $participant->side, $participant->userId, $latest, $now)]);
    }
}
