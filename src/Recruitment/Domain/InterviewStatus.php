<?php

namespace Src\Recruitment\Domain;

enum InterviewStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** The team recorded that the job seeker did not come to a confirmed interview. Not active: the application may get a new interview. */
    case NoShow = 'no_show';

    /** Pending and confirmed interviews are the live ones: an application has at most one. */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }
}
