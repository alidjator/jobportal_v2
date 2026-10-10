<?php

namespace Src\Review\Application;

use Src\Review\Domain\Reply;
use Src\Review\Domain\Review;

/** A review with the author's name (`Deleted user` for an erased account), the company's reply (when it may be shown) and the number of open reports (admins only). */
final readonly class ReviewView
{
    public const string DELETED_AUTHOR = 'Deleted user';

    public function __construct(
        public Review $review,
        public string $authorName,
        public ?Reply $reply,
        public int $openReports = 0,
    ) {}
}
