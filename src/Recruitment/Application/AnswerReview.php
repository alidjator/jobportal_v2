<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ReviewAnswer;
use Src\Recruitment\Domain\ReviewDecision;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\ReviewRequestStatus;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A reviewer answers `advance`, `decline` (with a reason) or `maybe` about an application of a review request, and may change the answer while the request is not closed. An answer never
 * moves the application. The request becomes `complete` when every reviewer answered every application that is still active.
 */
final readonly class AnswerReview
{
    public function __construct(
        private RecruitmentAccess $access,
        private ReviewRequestRepository $requests,
        private ApplicationRepository $applications,
        private ReviewRequestViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the request is not one the caller reviews, or the application is not one of it
     * @throws StateConflict `review-request-closed` or `application-final`
     * @throws BusinessRuleViolation `reason-required`
     */
    public function execute(Actor $actor, int $requestId, int $applicationId, ReviewDecision $decision, ?string $reason): ReviewRequestView
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsReview);

        $this->transactions->run(function () use ($member, $requestId, $applicationId, $decision, $reason): void {
            $request = $this->requests->find($requestId, forUpdate: true);

            if ($request === null || $request->companyId !== $member->companyId || ! in_array($member->userId, $this->requests->reviewerIds([$requestId])[$requestId] ?? [], true)) {
                throw EntityNotFound::of('ReviewRequest', $requestId);
            }

            $items = $this->requests->applicationIds([$requestId])[$requestId] ?? [];

            if (! in_array($applicationId, $items, true)) {
                throw EntityNotFound::of('Application', $applicationId);
            }

            if (! $request->acceptsAnswers()) {
                throw new StateConflict('This review request was closed.', 'review-request-closed');
            }

            $application = $this->applications->find($applicationId);

            if (! $application instanceof Application || $this->isFinal($application)) {
                throw new StateConflict('This application is final, so it needs no review.', 'application-final');
            }

            $now = $this->clock->now();
            $this->requests->saveAnswer(ReviewAnswer::give($requestId, $applicationId, $member->userId, $decision, $reason, $now));

            if ($request->status === ReviewRequestStatus::Open && $this->isComplete($requestId, $items)) {
                $this->requests->setStatus($requestId, ReviewRequestStatus::Complete, $now);
            }
        });

        return $this->views->of([$this->requests->find($requestId) ?? throw EntityNotFound::of('ReviewRequest', $requestId)], $member->userId)[0];
    }

    /** @param  list<int>  $items */
    private function isComplete(int $requestId, array $items): bool
    {
        $reviewers = $this->requests->reviewerIds([$requestId])[$requestId] ?? [];
        $given = [];

        foreach ($this->requests->answers([$requestId])[$requestId] ?? [] as $answer) {
            $given[$answer->applicationId][] = $answer->reviewerId;
        }

        foreach ($items as $applicationId) {
            $application = $this->applications->find($applicationId);

            if ($application instanceof Application && ! $this->isFinal($application) && array_diff($reviewers, $given[$applicationId] ?? []) !== []) {
                return false;
            }
        }

        return true;
    }

    private function isFinal(Application $application): bool
    {
        return $application->stageType->isTerminal() || $application->stageType === StageType::Hired;
    }
}
