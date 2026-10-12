<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;

/** An interview of an application with its result for one viewer: null while the viewer is on the panel and has not sent their scorecard. */
final readonly class ApplicationInterviewResult
{
    public function __construct(public Interview $interview, public int $panelSize, public ?InterviewResult $result) {}
}
