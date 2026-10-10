<?php

namespace Src\Review\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

interface ReviewRepository
{
    /**
     * @throws StateConflict `already-reviewed`
     */
    public function add(Review $review): Review;

    public function update(Review $review): void;

    public function delete(int $reviewId): void;

    public function find(int $reviewId, bool $forUpdate = false): ?Review;

    public function findOfUser(int $companyId, int $userId, bool $forUpdate = false): ?Review;

    /** @return Page<Review> only published reviews */
    public function publishedPage(int $companyId, ReviewSort $sort, PageRequest $request): Page;

    /** @return Page<Review> */
    public function pageOfUser(int $userId, PageRequest $request): Page;

    /** @return Page<Review> newest first */
    public function page(ReviewQuery $query, PageRequest $request): Page;

    /** The summary of the company's published reviews. */
    public function summary(int $companyId): ReviewSummary;

    /**
     * Erases the title and body of the user's published reviews (the ratings stay) and deletes their other reviews, with the replies.
     *
     * @return list<int> the companies whose published reviews changed
     */
    public function eraseOfUser(int $userId, DateTimeImmutable $now): array;
}
