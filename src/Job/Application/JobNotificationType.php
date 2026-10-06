<?php

namespace Src\Job\Application;

/** The notification types of the Job context. Clients write and translate the message; the server stores only the type and its data. */
final class JobNotificationType
{
    public const string ASSIGNED = 'job.assigned';

    public const string UNASSIGNED = 'job.unassigned';
}
