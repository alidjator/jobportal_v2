<?php

namespace Src\Review\Application;

use Src\Review\Domain\Reply;
use Src\Review\Domain\Review;
use Src\Shared\Application\Company\CompanySummary;

/** A review with the author's name (`Deleted user` for an erased account), the company's reply (when it may be shown), the number of open reports (admins only) and, for the author's own list, the company it is about. */
final readonly class ReviewView
{
    public const string DELETED_AUTHOR = 'Deleted user';

    public function __construct(
        public Review $review,
        public string $authorName,
        public ?Reply $reply,
        public int $openReports = 0,
        public ?CompanySummary $company = null,
    ) {}
}
