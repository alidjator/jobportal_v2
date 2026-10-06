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

    /** Whether the company can change the content of a job in this status: not while it waits for a decision, and not once an admin took it down. */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Rejected, self::Active, self::Closed, self::Expired => true,
            self::PendingReview, self::TakenDown => false,
        };
    }
}
