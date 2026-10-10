<?php

namespace Src\Review\Application;

use Src\Review\Domain\Reply;
use Src\Review\Domain\Review;

/** A reply with the review it answers, for the admin queue. */
final readonly class ReplyItem
{
    public function __construct(
        public Reply $reply,
        public Review $review,
    ) {}
}
