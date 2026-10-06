<?php

namespace Src\Job\Domain;

/** Where the work happens. The values match the check constraint of `job_jobs.work_mode`. */
enum WorkMode: string
{
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';
    case Remote = 'remote';
}
