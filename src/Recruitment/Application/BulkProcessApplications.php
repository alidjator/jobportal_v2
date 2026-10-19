<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use InvalidArgumentException;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewSlotPlanner;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A recruiter moves or rejects several applications of one job in one request. The request is checked as a whole first (selection, stage, shared input), and
 * a mistake there changes nothing. Then every application goes through {@see ProcessApplication} on its own, so each keeps the rules, history, notifications and
 * transaction of the single action and one that cannot be processed is reported and skipped without touching the others.
 *
 * A bulk move to an interview stage hands out slots from the base schedule in the order of the selection, skipping the interviewer's other interviews; the
 * slots are planned for the applications that can still be moved, so a skipped one leaves no hole (one that fails later leaves its slot unused). A bulk move
 * to an assessment stage sends the same test, window and assessors to every application, and a bulk move to a hired stage gives every application the same join date and note.
 */
final readonly class BulkProcessApplications
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(
        private RecruitmentAccess $access,
        private ProcessApplication $process,
        private ApplicationRepository $applications,
        private InterviewRepository $interviews,
        private InterviewScheduler $scheduler,
        private InterviewSlotPlanner $planner,
        private AssessmentInvitations $assessments,
        private JobDirectory $jobs,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $applicationIds
     * @return list<BulkItemResult> in the order of the selection
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the member may not see the job
     * @throws BusinessRuleViolation `invalid-applications`, `too-many-applications`, `invalid-stage`, `invalid-schedule`, `invalid-interview`, `invalid-interviewer`, `invalid-offer-input`, `invalid-offer`, `invalid-assessment-input`, `invalid-assessment-test`, `invalid-assessment-schedule`, `invalid-assessor`, `invalid-hire-input`, `join-date-required`, `invalid-join-date`, `invalid-hire-note` or `message-link-not-allowed`
     */
    public function move(Actor $actor, int $jobId, array $applicationIds, int $stageId, ?BulkInterviewInput $interview = null, ?OfferInput $offer = null, ?AssessmentInvitationInput $assessment = null, bool $scheduleLater = false, ?HireInput $hire = null): array
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        $states = $this->selection($member, $jobId, $applicationIds);
        $stage = $this->process->movableStage($member->companyId, $stageId);

        $this->assertInputFits($stage->type, $interview, $offer, $assessment, $scheduleLater);
        $this->process->hireFor($member, $stage->type, $hire);

        $movable = array_keys(array_filter($states, static fn (Application $state): bool => ! $state->stageType->isTerminal() && $state->stageId !== $stageId));
        $inputs = $interview instanceof BulkInterviewInput ? $this->interviewInputs($member, $interview, $movable) : [];

        if ($offer instanceof OfferInput) {
            $this->assertOffer($member, $jobId, $offer);
        }

        if ($assessment instanceof AssessmentInvitationInput) {
            $this->assessments->assertCanInvite($member->companyId, $jobId, $assessment);
        }

        return array_map(
            fn (int $id): BulkItemResult => $this->run(fn () => $this->process->move($actor, $id, $stageId, $inputs[$id] ?? null, $offer instanceof OfferInput && $stage->type === StageType::Offer ? $offer : null, $assessment, $scheduleLater, $hire), $id),
            $applicationIds,
        );
    }

    /**
     * @param  list<int>  $applicationIds
     * @return list<BulkItemResult> in the order of the selection
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the member may not see the job
     * @throws BusinessRuleViolation `invalid-applications`, `too-many-applications` or `invalid-rejection`
     */
    public function reject(Actor $actor, int $jobId, array $applicationIds, RejectionReason $reason, ?string $note): array
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        $this->selection($member, $jobId, $applicationIds);
        $note = $this->process->rejectionNote($reason, $note);

        return array_map(fn (int $id): BulkItemResult => $this->run(fn () => $this->process->reject($actor, $id, $reason, $note), $id), $applicationIds);
    }

    /** @param  callable(): (bool|void)  $action  true when the action filled the job */
    private function run(callable $action, int $applicationId): BulkItemResult
    {
        try {
            $filled = $action() === true;
        } catch (StateConflict|BusinessRuleViolation $error) {
            return new BulkItemResult($applicationId, $error->errorCode());
        } catch (EntityNotFound) {
            return new BulkItemResult($applicationId, 'application-not-found');
        }

        return new BulkItemResult($applicationId, jobFilled: $filled);
    }

    /**
     * Every id is an application of this job in the member's company, and the selection is within the cap.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, Application> the state of each application by id
     */
    private function selection(RecruitmentMember $member, int $jobId, array $applicationIds): array
    {
        $cap = $this->settings->integer(RecruitmentSettings::MAX_BULK_APPLICATIONS, $member->companyId);

        if (count($applicationIds) > $cap) {
            $this->refuse('too-many-applications', 'Select at most '.$cap.' applications at a time.', 'application_ids');
        }

        $states = [];

        foreach ($applicationIds as $id) {
            $state = $this->applications->find($id);

            if (! $state instanceof Application || $state->jobId !== $jobId || $state->companyId !== $member->companyId) {
                $this->refuse('invalid-applications', 'Select applications of this job only.', 'application_ids');
            }

            $states[$id] = $state;
        }

        return $states;
    }

    /** A schedule only goes with an interview stage, an offer with an offer stage and a test with an assessment stage, as in the single move. */
    private function assertInputFits(StageType $target, ?BulkInterviewInput $interview, ?OfferInput $offer, ?AssessmentInvitationInput $assessment, bool $scheduleLater = false): void
    {
        if ($scheduleLater && $interview instanceof BulkInterviewInput) {
            $this->refuse('invalid-interview', 'Give a schedule or choose to schedule later, not both.', 'interview');
        }

        if ($scheduleLater && $target !== StageType::Interview) {
            $this->refuse('invalid-schedule', 'An interview can only be scheduled later when moving to an interview stage.', 'interview');
        }

        if ($interview instanceof BulkInterviewInput && $target !== StageType::Interview) {
            $this->refuse('invalid-schedule', 'An interview can only be scheduled when moving to an interview stage.', 'interview');
        }

        if ($offer instanceof OfferInput && $target !== StageType::Offer) {
            $this->refuse('invalid-offer-input', 'An offer can only be sent when moving to an offer stage.', 'offer');
        }

        if ($assessment instanceof AssessmentInvitationInput && $target !== StageType::Assessment) {
            $this->refuse('invalid-assessment-input', 'A test can only be sent when moving to an assessment stage.', 'assessment');
        }
    }

    /**
     * Checks the base schedule with the rules of a single interview, then plans one slot for each application that can still be moved.
     *
     * @param  list<int>  $movable
     * @return array<int, InterviewInput> by application id
     */
    private function interviewInputs(RecruitmentMember $member, BulkInterviewInput $base, array $movable): array
    {
        $this->scheduler->assertInterviewer($member->companyId, $base->interviewerId);
        $this->scheduler->assertFormatAvailable($member->companyId, $base->format);

        [$location, $locationId] = $this->scheduler->place($member->companyId, $base->format, $base->location, $base->locationId);

        Interview::schedule(0, $base->startsAt, $base->durationMinutes, $base->format, $location, $base->meetingUrl, $base->interviewerId, $member->userId, $this->clock->now(), recorded: $base->recorded, note: $base->note);

        if ($this->minutes($base->dayEnd) <= $this->minutes($base->dayStart)) {
            $this->refuse('invalid-interview', 'The working day must end after it starts.', 'day_end');
        }

        try {
            $slots = $this->planner->plan(
                $base->startsAt, count($movable), $base->durationMinutes, $base->gapMinutes, $base->dayStart, $base->dayEnd, new DateTimeZone(self::ZONE),
                $this->interviews->busyRanges($base->interviewerId, $base->startsAt, $movable),
            );
        } catch (InvalidArgumentException) {
            $this->refuse('invalid-interview', 'The interview must fit in the working day.', 'duration_minutes');
        }

        $inputs = [];

        foreach ($movable as $index => $id) {
            $inputs[$id] = new InterviewInput($slots[$index][0], $base->durationMinutes, $base->format, $location, $base->meetingUrl, $base->interviewerId, recorded: $base->recorded, note: $base->note, noteGiven: $base->note !== null, locationId: $locationId);
        }

        return $inputs;
    }

    /** Checks the shared offer with the rules of a single offer. */
    private function assertOffer(RecruitmentMember $member, int $jobId, OfferInput $offer): void
    {
        Offer::make(0, $offer->position ?? $this->jobs->titles([$jobId])[$jobId] ?? '', $offer->salary, $offer->startDate, $offer->note, $offer->expiresAt, $member->userId, $this->clock->now());
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private function refuse(string $code, string $message, string $field): never
    {
        throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
    }
}
