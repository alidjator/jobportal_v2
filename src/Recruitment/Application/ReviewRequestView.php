<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewRequest;

/**
 * A review request as one viewer sees it. `$pendingForMe` counts the active applications the viewer still has to answer (null when they are not a reviewer).
 */
final readonly class ReviewRequestView
{
    /**
     * @param  list<array{id: int, name: ?string}>  $reviewers
     * @param  list<ReviewItemView>  $items
     * @param  array{answered: int, total: int}  $progress  the answers given and the answers awaited (reviewers times active applications)
     */
    public function __construct(
        public ReviewRequest $request,
        public ?string $jobTitle,
        public ?string $requesterName,
        public bool $overdue,
        public array $reviewers,
        public array $items,
        public array $progress,
        public ?int $pendingForMe,
    ) {}
}
