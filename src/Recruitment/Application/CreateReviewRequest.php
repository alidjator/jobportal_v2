<?php

namespace Src\Recruitment\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ReviewRequested;
use Src\Recruitment\Domain\ReviewRequest;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\ReviewRequestStatus;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A recruiter asks the hiring managers of a job to review chosen applications by a due date (`docs/design/phase-8-hiring-managers.md` section 3). The reviewers are hiring-manager
 * assignees of the job (all of them without a list); the applications are active ones of the job that the requester sees. Each application is shown to the hiring managers of the job
 * from now on (the marker is set), whatever its stage. The due date is a day in Jakarta time, from today to {@see ReviewRequest::DUE_MAX_DAYS} days ahead.
 */
final readonly class CreateReviewRequest
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
        private ReviewRequestRepository $requests,
        private HiringManagerVisibility $visibility,
        private JobDirectory $jobs,
        private ReviewRequestViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $applicationIds
     * @param  ?list<int>  $reviewerIds
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the job
     * @throws BusinessRuleViolation `invalid-due-date`, `invalid-reviewers` or `invalid-applications`
     */
    public function execute(Actor $actor, int $jobId, array $applicationIds, ?array $reviewerIds, string $dueOn, ?string $message): ReviewRequestView
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        $now = $this->clock->now();
        $due = $this->dueDate($dueOn, $this->views->today());
        $reviewers = $this->reviewers($jobId, $reviewerIds);
        $applicationIds = array_values(array_unique($applicationIds));
        $message = $message === null || trim($message) === '' ? null : trim($message);

        $saved = $this->transactions->run(function () use ($member, $jobId, $applicationIds, $reviewers, $due, $message, $now): ReviewRequest {
            foreach ($applicationIds as $applicationId) {
                $this->assertReviewable($member, $jobId, $applicationId);
            }

            $request = $this->requests->add(
                new ReviewRequest($member->companyId, $jobId, $member->userId, $message, $due, ReviewRequestStatus::Open, $now),
                $applicationIds,
                $reviewers,
            );

            foreach ($applicationIds as $applicationId) {
                $this->visibility->showForReview($applicationId, $now);
            }

            return $request;
        });

        $this->events->dispatch([new ReviewRequested((int) $saved->id, $now)]);

        return $this->views->of([$saved], $member->userId)[0];
    }

    /** @throws BusinessRuleViolation `invalid-due-date` */
    private function dueDate(string $dueOn, DateTimeImmutable $today): DateTimeImmutable
    {
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueOn, new DateTimeZone('Asia/Jakarta'));

        if ($due === false || $due < $today || $due > $today->add(new DateInterval('P'.ReviewRequest::DUE_MAX_DAYS.'D'))) {
            $message = 'Choose a due date from today to :days days ahead.';

            throw new BusinessRuleViolation($message, 'invalid-due-date', extensions: ['errors' => ['due_on' => [$message]]], parameters: ['days' => ReviewRequest::DUE_MAX_DAYS]);
        }

        return $due;
    }

    /**
     * @param  ?list<int>  $reviewerIds
     * @return list<int>
     *
     * @throws BusinessRuleViolation `invalid-reviewers`
     */
    private function reviewers(int $jobId, ?array $reviewerIds): array
    {
        $managers = $this->jobs->hiringManagerUserIds($jobId);
        $chosen = $reviewerIds === null ? $managers : array_values(array_unique($reviewerIds));

        if ($chosen === [] || array_diff($chosen, $managers) !== []) {
            $message = $managers === [] ? 'This job has no hiring manager to ask.' : 'Choose reviewers among the hiring managers of this job.';

            throw new BusinessRuleViolation($message, 'invalid-reviewers', extensions: ['errors' => ['reviewer_ids' => [$message]]]);
        }

        return $chosen;
    }

    /** @throws BusinessRuleViolation `invalid-applications` */
    private function assertReviewable(RecruitmentMember $member, int $jobId, int $applicationId): void
    {
        $application = $this->applications->find($applicationId);

        try {
            if (! $application instanceof Application || $application->jobId !== $jobId) {
                throw EntityNotFound::of('Application', $applicationId);
            }

            $this->applicationAccess->assertCanSee($member, $applicationId, $application->companyId, $jobId);
        } catch (EntityNotFound) {
            $this->invalid('Choose applications of this job.');
        }

        if ($application->stageType->isTerminal() || $application->stageType === StageType::Hired) {
            $this->invalid('Choose applications that are still active.');
        }
    }

    /** @throws BusinessRuleViolation */
    private function invalid(string $message): never
    {
        throw new BusinessRuleViolation($message, 'invalid-applications', extensions: ['errors' => ['application_ids' => [$message]]]);
    }
}
