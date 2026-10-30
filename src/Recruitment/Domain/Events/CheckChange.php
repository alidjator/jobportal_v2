<?php

namespace Src\Recruitment\Domain\Events;

enum CheckChange: string
{
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case ConsentRefused = 'consent_refused';
}
