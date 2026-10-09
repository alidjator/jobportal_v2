<?php

namespace Src\Messaging\Application;

/** A conversation with the names of both sides. `companyOnline` is only given to the job seeker (null for the company side). */
final readonly class ConversationView
{
    public function __construct(
        public ConversationRow $conversation,
        public string $companyName,
        public string $jobSeekerName,
        public ?MessageView $lastMessage,
        public ?bool $companyOnline,
    ) {}
}
