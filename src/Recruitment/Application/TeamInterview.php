<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Interview;

/** An interview together with its application and the member who may see it; `panelOnly` is set when the member reaches it through a panel and not through the job. */
final readonly class TeamInterview
{
    public function __construct(public RecruitmentMember $member, public Interview $interview, public Application $application, public bool $panelOnly = false) {}
}
