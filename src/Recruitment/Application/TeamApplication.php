<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;

/**
 * An application together with the member who may work on it. A panel-only member sees it through an interview panel and not through its job
 * (`docs/design/phase-8-interviewers.md` section 1): they get the reduced view, without notes and ratings.
 */
final readonly class TeamApplication
{
    public function __construct(public RecruitmentMember $member, public Application $application, public bool $panelOnly = false) {}
}
