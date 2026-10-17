<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The review page: the message, the due date and a card for each candidate with the answers under the lock. Open to the reviewers, to who made the request and to members with
 * `applications.manage` who see the job; for anyone else the request does not exist.
 */
final readonly class GetReviewRequest
{
    public function __construct(private RecruitmentAccess $access, private ReviewRequestRepository $requests, private ReviewRequestViews $views) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $requestId): ReviewRequestView
    {
        $member = $this->access->viewer($actor);
        $request = $this->requests->find($requestId);

        if ($request === null || $request->companyId !== $member->companyId) {
            throw EntityNotFound::of('ReviewRequest', $requestId);
        }

        $reviewer = in_array($member->userId, $this->requests->reviewerIds([$requestId])[$requestId] ?? [], true);

        if (! $reviewer && $request->requestedBy !== $member->userId) {
            if (! $member->membership->can(CompanyPermission::ApplicationsManage)) {
                throw EntityNotFound::of('ReviewRequest', $requestId);
            }

            $this->access->job($member, $request->jobId);
        }

        return $this->views->of([$request], $member->userId, withCards: true)[0];
    }
}
