<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationHireUpdated;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member who may move applications changes the join date or the note of a hired application (`docs/design/phase-8-hiring.md` section 1). Only the parts in the
 * request change. A change that leaves the details as they are saves, sends and audits nothing; any other tells the job seeker (a notice, and an email by the preference
 * `application_update`) and is audited with the old and the new details. A date that is not changed is never checked against the window again, so a note can still be
 * corrected on an old hire.
 */
final readonly class UpdateApplicationHire
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
        private JobDirectory $jobs,
        private CandidateMessageRules $messageRules,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see
     * @throws StateConflict `application-not-hired` or `job-taken-down`
     * @throws BusinessRuleViolation `join-date-required`, `invalid-join-date`, `invalid-hire-note` or `message-link-not-allowed`
     */
    public function execute(Actor $actor, int $applicationId, HireChange $change, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);

        $events = $this->transactions->run(function () use ($member, $applicationId, $change, $ipAddress, $userAgent): array {
            $application = $this->applications->find($applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $this->applicationAccess->assertCanSee($member, $applicationId, $application->companyId, $application->jobId);

            if ($this->jobs->isTakenDown($application->jobId)) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            if ($application->stageType !== StageType::Hired) {
                throw new StateConflict('Only a hired application has hire details.', 'application-not-hired');
            }

            $before = $this->applications->hireOf($applicationId);
            $after = $this->next($before, $change);

            if ($before instanceof HireDetails && $after->equals($before)) {
                return [];
            }

            if ($after->note !== $before?->note) {
                $this->messageRules->assertAllowed($application->companyId, $after->note, 'note');
            }

            $now = $this->clock->now();
            $this->applications->updateHire($applicationId, $after, $now);

            return [new ApplicationHireUpdated($applicationId, $application->jobId, $application->companyId, $application->userId, $member->userId, $before, $after, $ipAddress, $userAgent, $now)];
        });

        $this->events->dispatch($events);
    }

    private function next(?HireDetails $before, HireChange $change): HireDetails
    {
        $date = $change->joinDateGiven ? $change->joinDate : $before?->joinDate;
        $note = $change->noteGiven ? $change->note : $before?->note;

        if ($before instanceof HireDetails && $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $before->joinDate->format('Y-m-d')) {
            return new HireDetails($before->joinDate, HireDetails::cleanNote($note));
        }

        return HireDetails::make($date, $note, $this->clock->now());
    }
}
