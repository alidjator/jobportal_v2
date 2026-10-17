<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\ReviewRequestStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Ends a review request early, for the member who made it or anyone with `applications.manage`. Closing a closed request changes nothing; answers stay. */
final readonly class CloseReviewRequest
{
    public function __construct(
        private RecruitmentAccess $access,
        private ReviewRequestRepository $requests,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the request is not one the caller may close
     */
    public function execute(Actor $actor, int $requestId): void
    {
        $member = $this->access->viewer($actor);

        $this->transactions->run(function () use ($member, $requestId): void {
            $request = $this->requests->find($requestId, forUpdate: true);

            if ($request === null || $request->companyId !== $member->companyId || ($request->requestedBy !== $member->userId && ! $member->membership->can(CompanyPermission::ApplicationsManage))) {
                throw EntityNotFound::of('ReviewRequest', $requestId);
            }

            if ($request->status !== ReviewRequestStatus::Closed) {
                $this->requests->setStatus($requestId, ReviewRequestStatus::Closed, $this->clock->now());
            }
        });
    }
}
