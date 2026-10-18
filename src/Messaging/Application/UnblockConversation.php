<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\ConversationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The job seeker lifts their block: the company can write again (if the conversation is open). Unblocking an unblocked one changes nothing. */
final readonly class UnblockConversation
{
    public function __construct(
        private ConversationAccess $access,
        private ConversationRepository $conversations,
        private GetConversation $get,
        private TransactionManager $transactions,
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

            if ($conversation->isBlocked()) {
                $this->conversations->update($conversation->unblock());
            }
        });

        return $this->get->execute($actor, $conversationId);
    }
}
