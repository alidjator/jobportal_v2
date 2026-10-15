<?php

namespace Src\Job\Application;

/** The notification types of the Job context. Clients write and translate the message; the server stores only the type and its data. */
final class JobNotificationType
{
    public const string ASSIGNED = 'job.assigned';

    public const string UNASSIGNED = 'job.unassigned';

    public const string EXPIRED = 'job.expired';

    public const string APPROVED = 'job.approved';

    public const string REJECTED = 'job.rejected';

    public const string TAKEN_DOWN = 'job.taken_down';

    public const string RESTORED = 'job.restored';

    public const string HELD = 'job.held';

    /** Sent to a job seeker whose report was decided; it never says how. */
    public const string REPORT_REVIEWED = 'report.reviewed';
}
