<?php

namespace Src\Recruitment\Domain\Events;

enum OfferChange: string
{
    case Sent = 'sent';
    case Revised = 'revised';
    case Withdrawn = 'withdrawn';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expiring = 'expiring';
}
