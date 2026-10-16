<?php

namespace Src\Workforce\Domain;

/** Where a requisition stands (`docs/design/erp-requisitions.md` section 3). Only a draft or a rejected one can be edited; saving a rejected one makes it a draft again. */
enum RequisitionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case InRecruitment = 'in_recruitment';
    case Filled = 'filled';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    /** Whether jobs may be opened for it and it may still be amended or closed. */
    public function isOpen(): bool
    {
        return $this === self::Approved || $this === self::InRecruitment;
    }

    /** Whether a `planned` requisition in this status uses the quota of its plan line. */
    public function usesQuota(): bool
    {
        return in_array($this, [self::PendingApproval, self::Approved, self::InRecruitment, self::Filled], true);
    }
}
