<?php

namespace Src\Review\Domain;

/** A report waits (open) until an admin keeps the review (dismissed) or takes it down (actioned). */
enum ReportStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Actioned = 'actioned';
}
