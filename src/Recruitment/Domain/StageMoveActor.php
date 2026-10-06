<?php

namespace Src\Recruitment\Domain;

/** Who moved an application between stages. The values match the check constraint of `recruitment_application_stage_history.actor_kind`. */
enum StageMoveActor: string
{
    case Applicant = 'applicant';
    case Recruiter = 'recruiter';
    case System = 'system';
}
