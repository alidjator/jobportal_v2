<?php

namespace Src\Review\Domain;

/** The state of a review. Only a published one is public. */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Removed = 'removed';
}
