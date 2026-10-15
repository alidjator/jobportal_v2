<?php

namespace Src\Company\Domain;

/** Where a name change request stands. The values match the check constraint of `company_name_change_requests.status`. */
enum NameChangeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
