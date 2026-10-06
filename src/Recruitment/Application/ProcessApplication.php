<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A recruiter moves an application to another active stage or rejects it. Both lock the application row first, so a move that races with a withdrawal (or
 * with another recruiter) runs after it and meets its result. They work for the members who may see the job, on a job in any status but `taken_down`.
 */
final readonly class ProcessApplication
{
    public const int NOTE_MAX = 1000;

    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
        private StageRepository $stages,
        private JobDirectory $jobs,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see
     * @throws StateConflict `application-final`, `already-in-stage` or `job-taken-down`
     * @throws BusinessRuleViolation `invalid-stage`
     */
    public function move(Actor $actor, int $applicationId, int $stageId): void
    {
        $this->process($actor, $applicationId, function (Application $application) use ($stageId): array {
            $stage = $this->companyStage($application->companyId, $stageId) ?? throw new BusinessRuleViolation(
                'Choose one of your company\'s stages.', 'invalid-stage', extensions: ['errors' => ['stage_id' => ['Choose one of your company\'s stages.']]],
            );

            return [$application->moveTo($stageId, $stage->type), null, null];
        });
    }

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see
     * @throws StateConflict `application-final` or `job-taken-down`
     * @throws BusinessRuleViolation `invalid-rejection`
     */
    public function reject(Actor $actor, int $applicationId, RejectionReason $reason, ?string $note): void
    {
        $note = $this->rejectionNote($reason, $note);

        $this->process($actor, $applicationId, function (Application $application) use ($reason, $note): array {
            $rejected = $this->defaultStage($application->companyId, StageType::Rejected)
                ?? throw new StateConflict('This company cannot reject applications right now.', 'applications-unavailable');

            return [$application->reject((int) $rejected->id), $reason, $note];
        });
    }

    /**
     * @param  callable(Application): array{0: Application, 1: ?RejectionReason, 2: ?string}  $change
     */
    private function process(Actor $actor, int $applicationId, callable $change): void
    {
        $member = $this->access->member($actor);

        $event = $this->transactions->run(function () use ($member, $applicationId, $change): ApplicationStageChanged {
            $before = $this->applications->find($applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $this->applicationAccess->assertCanSee($member, $applicationId, $before->companyId, $before->jobId);

            if ($this->jobs->isTakenDown($before->jobId)) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            [$after, $reason, $note] = $change($before);
            $now = $this->clock->now();

            $this->applications->recordMove($before, $after, $member->userId, $reason, $note, $now);

            return new ApplicationStageChanged($applicationId, $before->jobId, $before->companyId, $before->userId, $before->stageType, $after->stageType, $now);
        });

        $this->events->dispatch([$event]);
    }

    private function companyStage(int $companyId, int $stageId): ?Stage
    {
        foreach ($this->stages->forCompany($companyId) as $stage) {
            if ($stage->id === $stageId) {
                return $stage;
            }
        }

        return null;
    }

    private function defaultStage(int $companyId, StageType $type): ?Stage
    {
        foreach ($this->stages->forCompany($companyId) as $stage) {
            if ($stage->isDefault && $stage->type === $type) {
                return $stage;
            }
        }

        return null;
    }

    /** `other` needs a note; any reason may carry one of at most 1,000 characters. */
    private function rejectionNote(RejectionReason $reason, ?string $note): ?string
    {
        $note = $note === null ? null : trim($note);
        $note = $note === '' ? null : $note;

        $message = match (true) {
            $note === null && $reason->requiresNote() => 'Say why in a note when the reason is "other".',
            $note !== null && mb_strlen($note) > self::NOTE_MAX => 'The note can have at most '.self::NOTE_MAX.' characters.',
            default => null,
        };

        if ($message !== null) {
            throw new BusinessRuleViolation($message, 'invalid-rejection', extensions: ['errors' => ['note' => [$message]]]);
        }

        return $note;
    }
}
