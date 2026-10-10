<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The acting job seeker's own reviews in every state, with the reply and its state. */
final readonly class ListMyReviews
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReviewViews $views,
    ) {}

    /**
     * @return Page<ReviewView>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor, PageRequest $request): Page
    {
        $page = $this->reviews->pageOfUser($this->gate->jobSeekerId($actor), $request);

        return new Page($this->views->of($page->items, false), $page->total, $request);
    }
}
