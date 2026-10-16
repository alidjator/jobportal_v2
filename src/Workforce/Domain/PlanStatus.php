<?php

namespace Src\Workforce\Domain;

/** Where a manpower plan stands. Only a draft or a rejected plan can be edited; saving a rejected plan makes it a draft again. */
enum PlanStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }
}
