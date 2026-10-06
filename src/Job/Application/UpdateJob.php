<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member changes some fields of a job they may see: `$changes` holds only what changes, keyed by content property name, and a null clears an optional
 * field. A job waiting for review or taken down cannot be edited. A live job must stay complete (its deadline range is checked only when the deadline
 * changes); when the company's settings require approval, a change to a core field sends it back to review. The job row is locked, so two edits of the
 * same job run one after the other. The slug follows the title.
 */
final readonly class UpdateJob
{
    public function __construct(
        private JobAccess $access,
        private JobContentChanges $changes,
        private JobCompleteness $completeness,
        private JobSlugGenerator $slugs,
        private JobRepository $jobs,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-editable`
     * @throws BusinessRuleViolation `invalid-job` or, for a live job, `job-incomplete`, with `errors` per field
     */
    public function execute(Actor $actor, int $jobId, array $changes, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($member, $jobId, $changes, $now, $ipAddress, $userAgent): ?JobStatusChanged {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);
            $job->assertEditable();

            $content = $this->changes->apply($job->content, $changes, isNew: false);
            $live = $job->status === JobStatus::Active;

            if ($live) {
                $this->completeness->assertComplete($content, $member->companyId, checkDeadlineRange: $content->deadline != $job->content->deadline);
            }

            $slug = $content->title === $job->content->title ? $job->slug : $this->slugs->make($content->title);
            $updated = $job->changeContent($slug, $content);

            $backToReview = $live
                && $content->coreDiffersFrom($job->content)
                && $this->settings->boolean(JobSettings::REQUIRE_APPROVAL, $member->companyId);

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
}
