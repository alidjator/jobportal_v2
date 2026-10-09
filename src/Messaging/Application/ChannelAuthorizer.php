<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Decides who may join the real-time channels. A refusal is a plain `false`: a channel the caller may not join is not told apart from one that does not exist. */
final readonly class ChannelAuthorizer
{
    public function __construct(private ConversationAccess $access) {}

    /** The private channel of one conversation: the same people who may read it. */
    public function mayJoinConversation(Actor $actor, int $conversationId): bool
    {
        try {
            $this->access->open($this->access->participant($actor), $conversationId);

            return true;
        } catch (AccessDenied|EntityNotFound) {
            return false;
        }
    }

    /** The presence channel of a company: only its own members, so a job seeker never appears to the company. */
    public function mayJoinCompanyPresence(Actor $actor, int $companyId): bool
    {
        try {
            $participant = $this->access->companyMember($actor);
        } catch (AccessDenied) {
            return false;
        }

        return $participant->companyId === $companyId;
    }
}
