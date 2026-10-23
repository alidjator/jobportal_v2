<?php

namespace Src\Job\Application;

use Src\Job\Application\Review\JobReviewPolicy;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Recruitment\JobApplications;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Application\Workforce\RequisitionGate;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member changes some fields of a job they may see: `$changes` holds only what changes, keyed by content property name, and a null clears an optional
 * field. A job waiting for review or taken down cannot be edited. A live job must stay complete (its deadline range is checked only when the deadline
 * changes); when the review policy asks for it (approval required, an unverified company, a suspicious text), a change to a core field sends it back to review. The job row is locked, so two edits of the
 * same job run one after the other. The slug follows the title. Once the job has applications its title, category, role, job type and level are locked.
 * The vacancies of a job opened for a requisition cannot exceed what the requisition has left (`requisition-vacancies-exceeded`).
 */
final readonly class UpdateJob
{
    public function __construct(
        private JobAccess $access,
        private JobContentChanges $changes,
        private JobCompleteness $completeness,
        private JobSlugGenerator $slugs,
        private JobRepository $jobs,
        private JobApplications $applications,
        private JobReviewPolicy $review,
        private RequisitionGate $requisitions,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-editable` or `job-locked-after-applications`, naming the locked fields
     * @throws BusinessRuleViolation `invalid-job` or, for a live job, `job-incomplete`, with `errors` per field
     */
    public function execute(Actor $actor, int $jobId, array $changes, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsCreate);
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($member, $jobId, $changes, $now, $ipAddress, $userAgent): ?JobStatusChanged {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);
            $job->assertEditable();

            $content = $this->changes->apply($job->content, $changes, isNew: false, companyId: $member->companyId);
            $this->assertPositionNotLocked($jobId, $content, $job->content);

            // The vacancies of a job opened for a requisition stay inside the hires of that requisition.
            if ($job->requisitionId !== null && $content->vacancies !== null && $content->vacancies !== $job->content->vacancies) {
                $this->requisitions->claim($member->companyId, $job->requisitionId, $content->vacancies, $jobId);
            }

            $live = $job->status === JobStatus::Active;

            if ($live) {
                $this->completeness->assertComplete($content, $member->companyId, checkDeadlineRange: $content->deadline != $job->content->deadline);
            }

            $slug = $content->title === $job->content->title ? $job->slug : $this->slugs->make($content->title);
            $updated = $job->changeContent($slug, $content);

            $backToReview = $live
                && $content->coreDiffersFrom($job->content)
                && $this->review->required($member->companyId, $content);

            if ($backToReview) {
                $updated = $updated->sendForReview();
            }

            $this->jobs->save($updated);

            return $backToReview
                ? new JobStatusChanged($jobId, $member->companyId, JobStatusChanged::SENT_FOR_REVIEW, $job->status->value, $updated->status->value, $member->userId, $ipAddress, $userAgent, $now)
                : null;
        });

        if ($event !== null) {
            $this->events->dispatch([$event]);
        }
    }

    /** Once a job has applications, what identifies the position (title, category, role, job type and level) cannot change. */
    private function assertPositionNotLocked(int $jobId, JobContent $content, JobContent $before): void
    {
        $fields = $content->lockedFieldsChangedFrom($before);

        if ($fields !== [] && $this->applications->hasApplications($jobId)) {
            throw new StateConflict(
                'This job already has applications, so :fields can no longer change.',
                'job-locked-after-applications',
                extensions: ['fields' => $fields],
                parameters: ['fields' => implode(', ', $fields)],
            );
        }
    }
}
