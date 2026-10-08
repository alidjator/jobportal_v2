<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;

/** An interview with the name of its interviewer (null when the account no longer has a name). */
final readonly class InterviewView
{
    public function __construct(public Interview $interview, public ?string $interviewerName) {}
}
