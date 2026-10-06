<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member changes some fields of a job they may see: `$changes` holds only what changes, keyed by content property name, and a null clears an optional
 * field. In this part only a draft or a rejected job can be edited (the other statuses come with publishing). The job row is locked, so two edits of
 * the same job run one after the other. The slug follows the title.
 */
final readonly class UpdateJob
{
    public function __construct(
        private JobAccess $access,
        private JobContentChanges $changes,
        private JobSlugGenerator $slugs,
        private JobRepository $jobs,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-editable`
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field
     */
    public function execute(Actor $actor, int $jobId, array $changes): void
    {
        $member = $this->access->member($actor);

        $this->transactions->run(function () use ($member, $jobId, $changes): void {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);
            $job->assertEditable();

            $content = $this->changes->apply($job->content, $changes, isNew: false);
            $slug = $content->title === $job->content->title ? $job->slug : $this->slugs->make($content->title);

            $this->jobs->save($job->changeContent($slug, $content));
        });
    }
}
