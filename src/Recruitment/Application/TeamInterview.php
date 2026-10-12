<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Interview;

/** An interview together with its application and the member who may see it. */
final readonly class TeamInterview
{
    public function __construct(public RecruitmentMember $member, public Interview $interview, public Application $application) {}
}
