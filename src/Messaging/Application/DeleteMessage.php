<?php

namespace Src\Messaging\Application;

use Psr\Log\LoggerInterface;
use Src\Messaging\Domain\Events\MessageDeleted;
use Src\Messaging\Domain\MessageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A sender deletes their own message within 15 minutes of sending it: the text and the files go, the row stays so the thread keeps its order. A file that other
 * messages still use (a bulk message) stays until the last of them goes. Deleting a message twice does nothing the second time.
 */
final readonly class DeleteMessage
{
    public function __construct(
        private ConversationAccess $access,
        private MessageRepository $messages,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Clock $clock,
        private EventDispatcher $events,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-participant`, `company-suspended` or `not-your-message`
     * @throws EntityNotFound
     * @throws StateConflict `delete-window-over`
     */
    public function execute(Actor $actor, int $messageId): void
    {
        $participant = $this->access->participant($actor);
        $message = $this->messages->findById($messageId) ?? throw EntityNotFound::of('Message', $messageId);
        $this->access->open($participant, $message->conversationId);

        if ($message->isDeleted()) {
            return;
        }

        $now = $this->clock->now();
        $message->assertCanBeDeletedBy($participant->userId, $now);

        $paths = $this->transactions->run(fn (): array => $this->messages->delete($messageId, $now));

        foreach ($paths as $path) {
            try {
                $this->files->delete($path);
            } catch (Throwable $e) {
                $this->logger->warning('The file of a deleted chat message could not be removed.', ['path' => $path, 'exception' => $e]);
            }
        }

        $this->events->dispatch([new MessageDeleted($message->conversationId, $messageId, $now)]);
    }
}
