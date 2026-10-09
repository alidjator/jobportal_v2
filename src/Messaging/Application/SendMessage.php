<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * Sends a message into a conversation the caller may see. The files are checked and stored first; if the send then fails they are removed again. The
 * conversation row is locked, so a close and a send cannot pass each other.
 */
final readonly class SendMessage
{
    public function __construct(
        private ConversationAccess $access,
        private MessageComposer $composer,
        private MessageReader $messages,
        private MessagingViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws AccessDenied `not-a-participant` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `empty-message`, `body-too-long`, `too-many-attachments`, `file-too-large` or `invalid-attachment`
     * @throws StateConflict `conversation-closed` or `recipient-unavailable`
     * @throws TooManyAttempts `daily-limit`
     */
    public function execute(Actor $actor, int $conversationId, MessageInput $input): MessageView
    {
        $sender = $this->access->participant($actor);
        $prepared = $this->composer->prepare($sender->userId, $input);

        try {
            $event = $this->transactions->run(function () use ($sender, $conversationId, $prepared) {
                $conversation = $this->access->open($sender, $conversationId, forUpdate: true);
                $this->composer->assertMayWrite($sender, $conversation);

                return $this->composer->deliver($conversation, $sender, $prepared);
            });
        } catch (Throwable $e) {
            $this->composer->discard($prepared);

            throw $e;
        }

        $this->events->dispatch([$event]);

        return $this->views->message($this->messages->one($event->messageId) ?? throw EntityNotFound::of('Message', $event->messageId));
    }
}
