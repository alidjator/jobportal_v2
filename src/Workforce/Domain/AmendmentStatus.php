<?php

namespace Src\Workforce\Domain;

enum AmendmentStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Withdrawn = 'withdrawn';
}
