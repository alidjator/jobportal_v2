<?php

namespace Src\Recruitment\Domain;

enum InterviewStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** Pending and confirmed interviews are the live ones: an application has at most one. */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }
}
