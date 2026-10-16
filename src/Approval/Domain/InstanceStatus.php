<?php

namespace Src\Approval\Domain;

/** Where a running approval stands: waiting for decisions, or ended (approved, rejected, returned for revision, or cancelled when the subject was withdrawn). */
enum InstanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
