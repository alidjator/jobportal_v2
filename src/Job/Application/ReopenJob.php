<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobRepository;
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
 * A member reopens a closed or an expired job they may see, with a new deadline. The job must still be complete (its fields may have been emptied while it was
 * off) and the deadline in range; it goes live at once, or to review when the company's settings require approval.
 */
final readonly class ReopenJob
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
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-reopenable`
     * @throws BusinessRuleViolation `job-incomplete` with `errors` per field
     */
    public function execute(Actor $actor, int $jobId, DateTimeImmutable $deadline, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($member, $jobId, $deadline, $now, $ipAddress, $userAgent): JobStatusChanged {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);
            $job->assertReopenable();

            $content = $job->content->with(['deadline' => $deadline]);
            $this->completeness->assertComplete($content, $member->companyId, checkDeadlineRange: true);

            $reopened = $job->changeContent($job->slug, $content)->reopen($this->settings->boolean(JobSettings::REQUIRE_APPROVAL, $member->companyId), $now);
            $this->jobs->save($reopened);

            return new JobStatusChanged($jobId, $member->companyId, JobStatusChanged::REOPENED, $job->status->value, $reopened->status->value, $member->userId, $ipAddress, $userAgent, $now);
        });

        $this->events->dispatch([$event]);
    }
}
