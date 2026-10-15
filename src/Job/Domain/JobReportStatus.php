<?php

namespace Src\Job\Domain;

/** Where a report stands: `open` until an admin dismisses it or takes the job down (`actioned`). The values match the check constraint of `job_reports.status`. */
enum JobReportStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Actioned = 'actioned';
}
