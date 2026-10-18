<?php

namespace Src\Messaging\Domain;

interface ConversationRepository
{
    public function findById(int $conversationId, bool $forUpdate = false): ?Conversation;

    public function findByPair(int $companyId, int $jobSeekerUserId, bool $forUpdate = false): ?Conversation;

    /** Saves the conversation, or returns the one the pair already has (two people starting a thread at the same moment end up in the same one). */
    public function addOrFind(Conversation $conversation): Conversation;

    /** Saves the closed and blocked state and the time of the last message of a conversation that exists. */
    public function update(Conversation $conversation): void;
}
