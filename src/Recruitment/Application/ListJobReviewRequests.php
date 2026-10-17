<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The review requests of a job with the answer of every reviewer about every application, for a member who may ask for reviews on it. Newest first. */
final readonly class ListJobReviewRequests
{
    public function __construct(private RecruitmentAccess $access, private ReviewRequestRepository $requests, private ReviewRequestViews $views) {}

    /**
     * @return Page<ReviewRequestView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the job
     */
    public function execute(Actor $actor, int $jobId, PageRequest $page): Page
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        $requests = $this->requests->forJob($jobId);

        return new Page($this->views->of(array_slice($requests, $page->offset(), $page->perPage), $member->userId), count($requests), $page);
    }
}
