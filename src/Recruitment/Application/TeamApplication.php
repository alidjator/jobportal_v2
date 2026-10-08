<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;

/** An application together with the member who may work on it. */
final readonly class TeamApplication
{
    public function __construct(public RecruitmentMember $member, public Application $application) {}
}
