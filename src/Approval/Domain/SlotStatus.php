<?php

namespace Src\Approval\Domain;

/** A slot is open, approved, or vacant (its account left the tenant) until an admin names a replacement. */
enum SlotStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Vacant = 'vacant';
}
