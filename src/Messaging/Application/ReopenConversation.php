<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\ConversationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company member reopens a closed conversation they may see. Reopening an open one changes nothing. */
final readonly class ReopenConversation
{
    public function __construct(
        private ConversationAccess $access,
        private ConversationRepository $conversations,
        private GetConversation $get,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $conversationId): ConversationView
    {
        $member = $this->access->companyMember($actor);

        $this->transactions->run(function () use ($member, $conversationId): void {
            $conversation = $this->access->open($member, $conversationId, forUpdate: true);

            if ($conversation->isClosed()) {
                $this->conversations->update($conversation->reopen());
            }
        });

        return $this->get->execute($actor, $conversationId);
    }
}
