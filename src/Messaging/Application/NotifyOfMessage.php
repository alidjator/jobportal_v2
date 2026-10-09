<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\ConversationRepository;
use Src\Messaging\Domain\Events\MessageSent;
use Src\Messaging\Domain\ReadStateRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\InAppNotifier;

/**
 * Tells the receiving side about a new message: their unread stretch starts (the reminder email counts from it) and one in-app notification per conversation is
 * written or refreshed while it is unread. The notification carries ids only, never the text or a name.
 */
final readonly class NotifyOfMessage
{
    public function __construct(
        private ConversationRepository $conversations,
        private MessageRecipients $recipients,
        private ReadStateRepository $reads,
        private InAppNotifier $notifier,
        private Clock $clock,
    ) {}

    public function inApp(MessageSent $event): void
    {
        $conversation = $this->conversations->findById($event->conversationId);

        if ($conversation === null) {
            return;
        }

        $userIds = $this->recipients->of($conversation, $event->side);

        if ($userIds === []) {
            return;
        }

        $this->reads->markUnread($event->conversationId, $userIds, $this->clock->now());
        $this->notifier->refresh($userIds, MessagingNotificationType::MESSAGE_RECEIVED, 'conversation_id', [
            'conversation_id' => $event->conversationId,
            'company_id' => $event->companyId,
            'message_id' => $event->messageId,
        ]);
    }
}
