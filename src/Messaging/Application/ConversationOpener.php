<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\Conversation;
use Src\Messaging\Domain\ConversationRepository;
use Src\Shared\Application\Clock;

/** Finds the conversation of a company and a job seeker, locked for the transaction, or creates it. Runs inside the caller's transaction. */
final readonly class ConversationOpener
{
    public function __construct(private ConversationRepository $conversations, private Clock $clock) {}

    /** @return array{Conversation, bool} the locked conversation and whether this call created it */
    public function openPair(int $companyId, int $jobSeekerUserId, int $startedByUserId): array
    {
        $existing = $this->conversations->findByPair($companyId, $jobSeekerUserId, forUpdate: true);

        if ($existing instanceof Conversation) {
            return [$existing, false];
        }

        $saved = $this->conversations->addOrFind(new Conversation($companyId, $jobSeekerUserId, $startedByUserId, $this->clock->now()));
        $locked = $this->conversations->findById((int) $saved->id, forUpdate: true) ?? $saved;

        return [$locked, $locked->startedByUserId === $startedByUserId];
    }
}
