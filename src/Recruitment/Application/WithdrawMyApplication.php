<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Assessment\InvitedApplication;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The applicant withdraws an application from any active stage except `hired`, with an optional note. The application moves to the company's default
 * `withdrawn` stage and frees the "one live application per job" key, so the applicant may apply to that job again. An active interview and an active test are cancelled and a pending offer withdrawn.
 */
final readonly class WithdrawMyApplication
{
    public const int NOTE_MAX = 1000;

    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private StageRepository $stages,
        private InterviewRepository $interviews,
        private OfferRepository $offers,
        private AssessmentInvitations $assessments,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `application-not-withdrawable`, `applications-unavailable`
     * @throws BusinessRuleViolation `invalid-withdrawal`
     */
    public function execute(Actor $actor, int $id, ?string $note): void
    {
        $userId = $this->guard->userId($actor);
        $note = $this->note($note);

        $this->transactions->run(function () use ($userId, $id, $note): void {
            $application = $this->applications->findOfApplicant($userId, $id, forUpdate: true) ?? throw EntityNotFound::of('Application', $id);

            $withdrawnStage = null;

            foreach ($this->stages->forCompany($application->companyId) as $stage) {
                if ($stage->isDefault && $stage->type === StageType::Withdrawn) {
                    $withdrawnStage = $stage;
                }
            }

            $withdrawn = $application->withdraw($withdrawnStage->id ?? throw new StateConflict('This company cannot take applications right now.', 'applications-unavailable'), $note);

            $this->applications->recordWithdrawal($application, $withdrawn, $this->clock->now());
            $this->interviews->cancelActive($id);
            $this->offers->withdrawActive($id);
            $this->assessments->cancelActive(new InvitedApplication($id, $application->companyId, $application->jobId, $application->userId));
        });
    }

    private function note(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        if ($note === null || $note === '') {
            return null;
        }

        if (mb_strlen($note) > self::NOTE_MAX) {
            $message = 'The note can have at most '.self::NOTE_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-withdrawal', extensions: ['errors' => ['note' => [$message]]]);
        }

        return $note;
    }
}
