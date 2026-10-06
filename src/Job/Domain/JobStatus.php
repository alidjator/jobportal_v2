<?php

namespace Src\Job\Domain;

/** Where a job stands. The values match the check constraint of `job_jobs.status`. Only `active` jobs are public and accept applications. */
enum JobStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Closed = 'closed';
    case Expired = 'expired';
    case Rejected = 'rejected';
    case TakenDown = 'taken_down';

    /** Whether the company can change the content of a job in this status (part 2: drafts and rejected jobs; the other statuses come with publishing). */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }
}
