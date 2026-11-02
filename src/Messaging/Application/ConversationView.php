<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Candidate\CandidateRef;

/**
 * A conversation with the names of both sides. `companyOnline` is only given to the job seeker (null for the company side). `candidate` is only given to the company side, and only while the job
 * seeker can be found by that company: the slug of their profile and whether they have a picture (`GET company/candidates/{slug}/avatar`).
 */
final readonly class ConversationView
{
    public function __construct(
        public ConversationRow $conversation,
        public string $companyName,
        public string $jobSeekerName,
        public ?MessageView $lastMessage,
        public ?bool $companyOnline,
        public ?CandidateRef $candidate = null,
    ) {}
}
