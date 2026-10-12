<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;

/** An interview together with the member who may see its application. */
final readonly class TeamInterview
{
    public function __construct(public RecruitmentMember $member, public Interview $interview) {}
}
