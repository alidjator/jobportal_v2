<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The review requests where the caller is a reviewer, the latest due date first. `pending`: an open request with an active application the caller has not answered; `answered`: every
 * other one (answered, closed, or nothing left to answer).
 */
final readonly class ListMyReviewRequests
{
    public const string PENDING = 'pending';

    public const string ANSWERED = 'answered';

    public function __construct(private RecruitmentAccess $access, private ReviewRequestRepository $requests, private ReviewRequestViews $views) {}

    /**
     * @return Page<ReviewRequestView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function execute(Actor $actor, ?string $state, PageRequest $page): Page
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsReview);

        $views = $this->views->of($this->requests->forReviewer($member->userId, $member->companyId), $member->userId);

        if ($state !== null) {
            $views = array_values(array_filter($views, static function (ReviewRequestView $view) use ($state): bool {
                $pending = $view->request->acceptsAnswers() && ($view->pendingForMe ?? 0) > 0;

                return $state === self::PENDING ? $pending : ! $pending;
            }));
        }

        return new Page(array_slice($views, $page->offset(), $page->perPage), count($views), $page);
    }
}
