<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A company member starts a job as a draft: only the title is required, and anything else sent is checked for validity (not completeness). The creator
 * is assigned to the job at once, so a recruiter can keep working on what they started.
 */
final readonly class CreateJob
{
    public function __construct(
        private JobAccess $access,
        private JobContentChanges $changes,
        private JobSlugGenerator $slugs,
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $fields  content property changes besides the title (camel case names; `tags` as raw text)
     * @return int the id of the new job
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field
     */
    public function execute(Actor $actor, string $title, array $fields): int
    {
        $member = $this->access->member($actor);
        $content = $this->changes->apply(JobContent::draft($title), $fields, isNew: true);

        return $this->transactions->run(function () use ($member, $content): int {
            $job = $this->jobs->create(Job::draft($member->companyId, $this->slugs->make($content->title), $content, $member->userId));
            $this->assignments->add((int) $job->id, $member->userId, null, $this->clock->now());

            return (int) $job->id;
        });
    }
}
