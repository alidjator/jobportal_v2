<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;

/**
 * An interview with the name of its interviewer (null when the account no longer has a name). `$participants` are the extra panel members, filled only
 * for the team: the job seeker never sees the panel.
 */
final readonly class InterviewView
{
    /** @param  list<array{id: int, name: ?string}>  $participants */
    public function __construct(public Interview $interview, public ?string $interviewerName, public array $participants = []) {}
}
