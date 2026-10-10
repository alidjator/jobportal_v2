<?php

namespace Src\Review\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetMyReview
{
    public function __construct(
        private OwnReviews $own,
        private ReviewViews $views,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $reviewId): ReviewView
    {
        return $this->views->one($this->own->find($actor, $reviewId), false);
    }
}
