<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\ConversationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The job seeker blocks a conversation: the company can then not send (409 `conversation-blocked`, bulk messages skip it) until they unblock; they keep reading. Blocking a blocked one changes nothing. */
final readonly class BlockConversation
{
    public function __construct(
        private ConversationAccess $access,
        private ConversationRepository $conversations,
        private GetConversation $get,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $conversationId): ConversationView
    {
        $seeker = $this->access->jobSeeker($actor);

        $this->transactions->run(function () use ($seeker, $conversationId): void {
            $conversation = $this->access->open($seeker, $conversationId, forUpdate: true);

            if (! $conversation->isBlocked()) {
                $this->conversations->update($conversation->blockBySeeker($this->clock->now()));
            }
        });

        return $this->get->execute($actor, $conversationId);
    }
}
