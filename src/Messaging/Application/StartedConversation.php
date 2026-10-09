<?php

namespace Src\Messaging\Application;

/** The answer to starting a conversation: it, and whether it is new (false when the pair already had one and the message went into it). */
final readonly class StartedConversation
{
    public function __construct(public ConversationView $view, public bool $created) {}
}
