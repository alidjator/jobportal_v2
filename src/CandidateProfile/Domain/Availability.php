<?php

namespace Src\CandidateProfile\Domain;

/** Nilai kolom `candidates.status`. */
enum Availability: string
{
    case Available = 'available';
    case NotAvailable = 'not_available';
    case AvailableIn = 'available_in';
}
