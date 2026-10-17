<?php

namespace Src\Recruitment\Domain;

/** What a hiring manager answers about a candidate of a shortlist review: go on, no, or maybe. */
enum ReviewDecision: string
{
    case Advance = 'advance';
    case Decline = 'decline';
    case Maybe = 'maybe';
}
