<?php

namespace Src\JobAlert\Domain;

/** How soon a job alert tells its owner: at once when a matching job goes live, or in one summary a day (08:00) or a week (Monday 08:00), Jakarta time. */
enum AlertFrequency: string
{
    case Immediate = 'immediate';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
