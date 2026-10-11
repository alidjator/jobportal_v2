<?php

namespace Src\Assessment\Application\Review;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One report for review, for a psychologist of the provider that owns the battery. */
final readonly class GetReview
{
    public function __construct(private ReviewAccess $access, private ReviewViews $views) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $reportId): ReviewView
    {
        $psychologist = $this->access->psychologist($actor);

        return $this->views->build($psychologist, $this->access->report($psychologist, $reportId));
    }
}
