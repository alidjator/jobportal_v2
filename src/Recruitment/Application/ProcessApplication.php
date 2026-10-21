<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Events\JobFilled;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Assessment\InvitedApplication;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A recruiter moves an application to another active stage or rejects it. Both lock the application row first, so a move that races with a withdrawal (or
 * with another recruiter) runs after it and meets its result. They work for the members who may see the job, on a job in any status but `taken_down`.
 *
 * A move to an interview stage needs an interview schedule (the one in the request, or an active one the application already has) and saves it with the move;
 * a move to an offer stage needs an offer the same way. A move to an interview stage may say instead that the time comes later (`scheduleLater`): no interview is
 * created, and the application shows as not scheduled until `PUT .../interview` sets one. A rejection cancels the active interview and withdraws the pending offer, and so does a move out of
 * the offer stage (an offer only lives while the application is in an offer stage).
 *
 * A move to an assessment stage needs a test to send (the one in the request, or an active one the application already has); a move to any other stage, and a
 * rejection, cancels the active test.
 *
 * A move to a hired stage needs the hire details (join date, optional note for the candidate), saved with the move; they are cleared when the application
 * moves out of `hired` again (`docs/design/phase-8-hiring.md` section 1). A move that brings the job's hires up to its vacancies also dispatches
 * {@see JobFilled} and says so in its return value (section 2).
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
        private InterviewRepository $interviews,
        private InterviewScheduler $scheduler,
        private OfferRepository $offers,
        private OfferPlacer $offerPlacer,
        private AssessmentInvitations $assessments,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
        private CandidateMessageRules $messageRules,
        private WatchJobFill $fill,
    ) {}

    /**
     * @return bool whether this hire filled the job (its hires reached its vacancies), so the team can be offered to close it
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see
     * @throws StateConflict `application-final`, `already-in-stage`, `job-taken-down` or `invitation-in-progress`
     * @throws BusinessRuleViolation `invalid-stage`, `schedule-required`, `invalid-interview` (a schedule and "later" together), `invalid-schedule`, `invalid-interview`, `invalid-interviewer`, `offer-required`, `invalid-offer-input`, `invalid-offer`, `assessment-required`, `invalid-assessment-input`, `invalid-assessment-test`, `invalid-assessment-schedule`, `invalid-assessor`, `invalid-hire-input`, `join-date-required`, `invalid-join-date`, `invalid-hire-note` or `message-link-not-allowed`
     */
    public function move(Actor $actor, int $applicationId, int $stageId, ?InterviewInput $interview = null, ?OfferInput $offer = null, ?AssessmentInvitationInput $assessment = null, bool $scheduleLater = false, ?HireInput $hire = null): bool
    {
        return $this->process($actor, $applicationId, function (Application $application, RecruitmentMember $member) use ($stageId, $interview, $offer, $assessment, $scheduleLater, $hire): array {
            $stage = $this->movableStage($application->companyId, $stageId);

            $moved = $application->moveTo($stageId, $stage->type);

            $extra = [...$this->interviewFor($member, $moved, $stage->type, $interview, $scheduleLater), ...$this->offerFor($member, $moved, $stage->type, $offer), ...$this->assessmentFor($member, $moved, $stage->type, $assessment)];

            return [$moved, null, null, $extra, $this->hireFor($member, $stage->type, $hire)];
        });
    }

    /**
     * The company's stage a recruiter may move an application to (an active stage, never `rejected` or `withdrawn`).
     *
     * @throws BusinessRuleViolation `invalid-stage`
     */
    public function movableStage(int $companyId, int $stageId): Stage
    {
        $stage = $this->companyStage($companyId, $stageId);

        if (! $stage instanceof Stage || ! $stage->type->acceptsRecruiterMoves()) {
            $message = $stage instanceof Stage ? 'An application cannot be moved to this stage.' : 'Choose one of your company\'s stages.';

            throw new BusinessRuleViolation($message, 'invalid-stage', extensions: ['errors' => ['stage_id' => [$message]]]);
        }

        return $stage;
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see
     * @throws StateConflict `application-final`, `job-taken-down`, or `offer-pending` when `$sparePendingOffer` is set and the application has a pending offer
     * @throws BusinessRuleViolation `invalid-rejection`
     */
    public function reject(Actor $actor, int $applicationId, RejectionReason $reason, ?string $note, bool $sparePendingOffer = false): void
    {
        $note = $this->rejectionNote($reason, $note);

        $this->process($actor, $applicationId, function (Application $application) use ($reason, $note, $sparePendingOffer): array {
            if ($sparePendingOffer) {
                $this->assertNoPendingOffer($application);
            }

            $rejected = $this->defaultStage($application->companyId, StageType::Rejected)
                ?? throw new StateConflict('This company cannot reject applications right now.', 'applications-unavailable');

            $cancelled = $this->interviews->cancelActive((int) $application->id);
            $extra = $cancelled instanceof Interview ? [InterviewChanged::of(InterviewChange::Cancelled, $application, $cancelled, $this->clock->now())] : [];

            return [$application->reject((int) $rejected->id), $reason, $note, [...$extra, ...$this->withdrawOffer($application), ...$this->assessments->cancelActive($this->invited($application))], null];
        });
    }

    /**
     * @param  callable(Application, RecruitmentMember): array{0: Application, 1: ?RejectionReason, 2: ?string, 3: list<DomainEvent>, 4: ?HireDetails}  $change
     * @return bool whether the change filled the job
     */
    private function process(Actor $actor, int $applicationId, callable $change): bool
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);

        $events = $this->transactions->run(function () use ($member, $applicationId, $change): array {
            $before = $this->applications->find($applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $this->applicationAccess->assertCanSee($member, $applicationId, $before->companyId, $before->jobId);

            if ($this->jobs->isTakenDown($before->jobId)) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            [$after, $reason, $note, $extra, $hire] = $change($before, $member);
            $now = $this->clock->now();

            $this->applications->recordMove($before, $after, $member->userId, $reason, $note, $now, $hire);

            $filled = $this->fill->afterMove($before, $after, $now);

            return [new ApplicationStageChanged($applicationId, $before->jobId, $before->companyId, $before->userId, $before->stageType, $after->stageType, $now, $hire, $this->noticeCovered($after->stageType, $extra)), ...$extra, ...($filled instanceof JobFilled ? [$filled] : [])];
        });

        $this->events->dispatch($events);

        return array_filter($events, static fn (DomainEvent $event): bool => $event instanceof JobFilled) !== [];
    }

    /**
     * Whether the offer or the interview of this move already tells the applicant, so the stage email and notice would be a second message: a new offer
     * (sent or revised) for a move into an offer stage, a newly scheduled interview for a move into an interview stage.
     *
     * @param  list<DomainEvent>  $extra
     */
    private function noticeCovered(StageType $target, array $extra): bool
    {
        foreach ($extra as $event) {
            if ($target === StageType::Offer && $event instanceof OfferChanged && in_array($event->change, [OfferChange::Sent, OfferChange::Revised], true)) {
                return true;
            }

            if ($target === StageType::Interview && $event instanceof InterviewChanged && in_array($event->change, [InterviewChange::Scheduled, InterviewChange::Rescheduled], true)) {
                return true;
            }
        }

        return false;
    }

    /** @throws StateConflict `offer-pending` */
    private function assertNoPendingOffer(Application $application): void
    {
        $offer = $this->offers->active((int) $application->id);

        if ($offer instanceof Offer && $offer->isOpenAt($this->clock->now())) {
            throw new StateConflict('This application has a pending offer.', 'offer-pending');
        }
    }

    /**
     * The interview of a move: the one in the request is saved (it replaces an active one); a move to an interview stage without one needs an active interview
     * already; a schedule for any other stage is refused.
     *
     * @return list<DomainEvent>
     */
    private function interviewFor(RecruitmentMember $member, Application $application, StageType $target, ?InterviewInput $input, bool $scheduleLater = false): array
    {
        if ($scheduleLater && $input instanceof InterviewInput) {
            $this->refuse('invalid-interview', 'Give a schedule or choose to schedule later, not both.');
        }

        if ($target !== StageType::Interview) {
            if ($input instanceof InterviewInput || $scheduleLater) {
                $this->refuse('invalid-schedule', 'An interview can only be scheduled when moving to an interview stage.');
            }

            return [];
        }

        if ($input instanceof InterviewInput) {
            return [$this->scheduler->schedule($member, $application, $input)];
        }

        if (! $scheduleLater && $this->interviews->active((int) $application->id) === null) {
            $this->refuse('schedule-required', 'Schedule the interview when moving an application to an interview stage.');
        }

        return [];
    }

    /**
     * The offer of a move, like the interview: the one in the request is saved (it replaces a pending one); a move to an offer stage without one needs a
     * pending offer already; an offer for any other stage is refused, and a move out of the offer stage withdraws the pending offer.
     *
     * @return list<DomainEvent>
     */
    private function offerFor(RecruitmentMember $member, Application $application, StageType $target, ?OfferInput $input): array
    {
        if ($target !== StageType::Offer) {
            if ($input instanceof OfferInput) {
                $this->refuse('invalid-offer-input', 'An offer can only be sent when moving to an offer stage.', 'offer');
            }

            return $this->withdrawOffer($application);
        }

        if ($input instanceof OfferInput) {
            return [$this->offerPlacer->place($member, $application, $input)];
        }

        $active = $this->offers->active((int) $application->id);

        if (! $active instanceof Offer || ! $active->isOpenAt($this->clock->now())) {
            $this->refuse('offer-required', 'Send the offer when moving an application to an offer stage.', 'offer');
        }

        return [];
    }

    /**
     * The test of a move, like the interview: the one in the request is sent (it replaces an active one); a move to an assessment stage without one needs an
     * active test already; a test for any other stage is refused, and a move out of an assessment stage cancels the active test.
     *
     * @return list<DomainEvent>
     */
    private function assessmentFor(RecruitmentMember $member, Application $application, StageType $target, ?AssessmentInvitationInput $input): array
    {
        if ($target !== StageType::Assessment) {
            if ($input instanceof AssessmentInvitationInput) {
                $this->refuse('invalid-assessment-input', 'A test can only be sent when moving to an assessment stage.', 'assessment');
            }

            return $this->assessments->cancelActive($this->invited($application));
        }

        if ($input instanceof AssessmentInvitationInput) {
            return $this->assessments->invite($this->invited($application), $member->userId, $input);
        }

        if (! $this->assessments->hasActive((int) $application->id)) {
            $this->refuse('assessment-required', 'Send a test when moving an application to an assessment stage.', 'assessment');
        }

        return [];
    }

    /**
     * The hire details of a move: a move to a hired stage needs them (the date is checked here, the note by the link rule); a move to any other stage refuses them.
     *
     * @throws BusinessRuleViolation `invalid-hire-input`, `join-date-required`, `invalid-join-date`, `invalid-hire-note` or `message-link-not-allowed`
     */
    public function hireFor(RecruitmentMember $member, StageType $target, ?HireInput $input): ?HireDetails
    {
        if ($target !== StageType::Hired) {
            if ($input instanceof HireInput) {
                $this->refuse('invalid-hire-input', 'Hire details can only be given when moving to a hired stage.', 'hire');
            }

            return null;
        }

        $details = HireDetails::make($input?->joinDate, $input?->note, $this->clock->now(), 'hire.');
        $this->messageRules->assertAllowed($member->companyId, $details->note, 'hire.note');

        return $details;
    }

    private function invited(Application $application): InvitedApplication
    {
        return new InvitedApplication((int) $application->id, $application->companyId, $application->jobId, $application->userId);
    }

    /** @return list<DomainEvent> */
    private function withdrawOffer(Application $application): array
    {
        $withdrawn = $this->offers->withdrawActive((int) $application->id);

        return $withdrawn instanceof Offer ? [OfferChanged::of(OfferChange::Withdrawn, $application, $withdrawn, $this->clock->now())] : [];
    }

    private function refuse(string $code, string $message, string $field = 'interview'): never
    {
        throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
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

    /**
     * `other` needs a note; any reason may carry one of at most 1,000 characters.
     *
     * @throws BusinessRuleViolation `invalid-rejection`
     */
    public function rejectionNote(RejectionReason $reason, ?string $note): ?string
    {
        $note = $note === null ? null : trim($note);
        $note = $note === '' ? null : $note;

        $message = match (true) {
            $note === null && $reason->requiresNote() => 'Say why in a note when the reason is "other".',
            $note !== null && mb_strlen($note) > self::NOTE_MAX => 'The note can have at most :max characters.',
            default => null,
        };

        if ($message !== null) {
            throw new BusinessRuleViolation($message, 'invalid-rejection', extensions: ['errors' => ['note' => [$message]]], parameters: ['max' => self::NOTE_MAX]);
        }

        return $note;
    }
}
