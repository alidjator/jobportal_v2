<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\RsvpAnswer;

/**
 * An interview with the name of its interviewer (null when the account no longer has a name). `$participants` are the extra panel members, filled only
 * for the team: the job seeker never sees the panel. The team also sees the answer of the main interviewer and of each extra member to the invitation.
 */
final readonly class InterviewView
{
    /** @param  list<array{id: int, name: ?string, rsvp: RsvpAnswer}>  $participants */
    public function __construct(public Interview $interview, public ?string $interviewerName, public array $participants = [], public ?RsvpAnswer $interviewerRsvp = null) {}
}
