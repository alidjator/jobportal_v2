<?php

namespace Src\Recruitment\Domain;

enum OfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    /** A pending offer is the live one: an application has at most one. */
    public function isActive(): bool
    {
        return $this === self::Pending;
    }
}
