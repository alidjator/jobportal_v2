<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The review requests where the caller is a reviewer (the default) or the requester, the latest due date first. State: `pending` is an open request with an active application the caller has
 * not answered, `answered` every other one (answered, closed, or nothing left to answer), `overdue` an open request past its due day. The requester role needs `applications.manage`.
 */
final readonly class ListMyReviewRequests
{
    public const string PENDING = 'pending';

    public const string ANSWERED = 'answered';

    public const string OVERDUE = 'overdue';

    public const string ROLE_REVIEWER = 'reviewer';

    public const string ROLE_REQUESTER = 'requester';

    public function __construct(private RecruitmentAccess $access, private ReviewRequestRepository $requests, private ReviewRequestViews $views) {}

    /**
     * @return Page<ReviewRequestView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function execute(Actor $actor, ?string $state, string $role, PageRequest $page): Page
    {
        $asRequester = $role === self::ROLE_REQUESTER;
        $member = $this->access->viewer($actor, $asRequester ? CompanyPermission::ApplicationsManage : CompanyPermission::ApplicationsReview);

        $views = $this->views->of($asRequester ? $this->requests->forRequester($member->userId, $member->companyId) : $this->requests->forReviewer($member->userId, $member->companyId), $member->userId);

        if ($state !== null) {
            $views = array_values(array_filter($views, static function (ReviewRequestView $view) use ($state): bool {
                if ($state === self::OVERDUE) {
                    return $view->overdue;
                }

                $pending = $view->request->acceptsAnswers() && ($view->pendingForMe ?? 0) > 0;

                return $state === self::PENDING ? $pending : ! $pending;
            }));
        }

        return new Page(array_slice($views, $page->offset(), $page->perPage), count($views), $page);
    }
}
