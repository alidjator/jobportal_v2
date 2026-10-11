<?php

namespace Src\Assessment\Domain;

/** A report is a draft awaiting the psychologist until it is signed; a signed report is final. */
enum ReportStatus: string
{
    case PendingReview = 'pending_review';
    case Signed = 'signed';
}
