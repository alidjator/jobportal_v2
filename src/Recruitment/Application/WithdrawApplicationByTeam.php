<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Domain\WithdrawalReason;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Assessment\InvitedApplication;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A team member marks an application withdrawn for a candidate who backed out outside the platform (`docs/design/phase-8-withdrawals.md` section 2). The effects are
 * those of the job seeker's own withdrawal: the company's default `withdrawn` stage, the freed key, the active interview and test cancelled, the pending offer
 * withdrawn. The reason is required (`other` needs a note); the note is the team's own and stays with the history entry. The job seeker is told by one notice and
 * one email, so the cancelled interview, offer and test send no messages of their own.
 */
final readonly class WithdrawApplicationByTeam
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
        private StageRepository $stages,
        private JobDirectory $jobs,
        private InterviewRepository $interviews,
        private OfferRepository $offers,
        private AssessmentInvitations $assessments,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see
     * @throws StateConflict `application-not-withdrawable`, `job-taken-down` or `applications-unavailable`
     * @throws BusinessRuleViolation `invalid-withdrawal`
     */
    public function execute(Actor $actor, int $applicationId, ?string $reason, ?string $note, ?string $ipAddress, ?string $userAgent): void
    {
        $reason = WithdrawalReason::fromRequest($reason, byTeam: true, code: 'invalid-withdrawal') ?? throw new BusinessRuleViolation('The reason is required.', 'invalid-withdrawal');
        $note = $this->note($reason, $note);
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);

        $events = $this->transactions->run(function () use ($member, $applicationId, $reason, $note, $ipAddress, $userAgent): array {
            $before = $this->applications->find($applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $this->applicationAccess->assertCanSee($member, $applicationId, $before->companyId, $before->jobId);

            if ($this->jobs->isTakenDown($before->jobId)) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            $stage = null;

            foreach ($this->stages->forCompany($before->companyId) as $candidate) {
                if ($candidate->isDefault && $candidate->type === StageType::Withdrawn) {
                    $stage = $candidate;
                }
            }

            $after = $before->withdraw($stage->id ?? throw new StateConflict('This company cannot take applications right now.', 'applications-unavailable'), null);
            $now = $this->clock->now();

            $this->applications->recordWithdrawalByTeam($before, $after, $member->userId, $reason, $note, $now);

            $cancelled = $this->interviews->cancelActive($applicationId);
            $this->offers->withdrawActive($applicationId);
            $this->assessments->cancelActive(new InvitedApplication($applicationId, $before->companyId, $before->jobId, $before->userId));

            return [
                new ApplicationStageChanged($applicationId, $before->jobId, $before->companyId, $before->userId, $before->stageType, $after->stageType, $now),
                new ApplicationWithdrawn(
                    $applicationId, $before->jobId, $before->companyId, $before->userId, StageMoveActor::Recruiter, $reason, $member->userId, [$member->userId],
                    $cancelled instanceof Interview ? (int) $cancelled->id : null, $ipAddress, $userAgent, $now,
                ),
            ];
        });

        $this->events->dispatch($events);
    }

    private function note(WithdrawalReason $reason, ?string $note): ?string
    {
        $note = $note === null ? null : trim($note);
        $note = $note === '' ? null : $note;

        $message = match (true) {
            $note === null && $reason->requiresNoteFromTeam() => 'A note is required for this reason.',
            $note !== null && mb_strlen($note) > WithdrawMyApplication::NOTE_MAX => 'The note can have at most '.WithdrawMyApplication::NOTE_MAX.' characters.',
            default => null,
        };

        if ($message !== null) {
            throw new BusinessRuleViolation($message, 'invalid-withdrawal', extensions: ['errors' => ['note' => [$message]]]);
        }

        return $note;
    }
}
