<?php

namespace Src\Job\Domain;

/** What an assignee does on a job: a recruiter works the whole pipeline, a hiring manager sees applications only from the stage boundary of the job (`phase-8-hiring-managers.md`). */
enum JobAssignmentRole: string
{
    case Recruiter = 'recruiter';
    case HiringManager = 'hiring_manager';
}
