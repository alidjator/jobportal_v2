<?php

namespace Src\Recruitment\Domain;

/** The answer of a panel member to the invitation to an interview. */
enum Rsvp: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
