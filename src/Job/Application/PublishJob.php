<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member publishes a draft or a rejected job they may see. The job must be complete and its deadline in range; it goes live at once, or to review
 * when the company's settings require approval. The status is checked before the content (409 before 422).
 */
final readonly class PublishJob
{
    public function __construct(
        private JobAccess $access,
        private JobCompleteness $completeness,
        private JobRepository $jobs,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-publishable`
     * @throws BusinessRuleViolation `job-incomplete` with `errors` per field
     */
    public function execute(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsPublish);
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($member, $jobId, $now, $ipAddress, $userAgent): JobStatusChanged {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);

            $published = $job->publish($this->settings->boolean(JobSettings::REQUIRE_APPROVAL, $member->companyId), $now);
            $this->completeness->assertComplete($job->content, $member->companyId, checkDeadlineRange: true);
            $this->jobs->save($published);

            return new JobStatusChanged($jobId, $member->companyId, JobStatusChanged::PUBLISHED, $job->status->value, $published->status->value, $member->userId, $ipAddress, $userAgent, $now);
        });

        $this->events->dispatch([$event]);
    }
}
