<?php

namespace Src\Review\Domain;

/** The state of a company's reply to a review. Only a published one is public. */
enum ReplyStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
}
