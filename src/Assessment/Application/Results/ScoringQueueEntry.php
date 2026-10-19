<?php

namespace Src\Assessment\Application\Results;

use DateTimeImmutable;
use Src\Assessment\Application\Delivery\InvitationView;

/** One test waiting for the member to score: the invitation as the team sees it, the candidate's name, the job title and when the candidate submitted. */
final readonly class ScoringQueueEntry
{
    public function __construct(public InvitationView $view, public ?string $candidateName, public string $jobTitle, public DateTimeImmutable $submittedAt) {}
}
