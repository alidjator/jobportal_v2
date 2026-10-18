<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Recruitment\Domain\Events\JobFilled;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Domain\WithdrawalReason;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMessageTexts;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The job seeker answers the pending offer of their own application. Accepting moves the application to the company's default `hired` stage; declining moves it
 * to the default `withdrawn` stage, with the optional note, which frees the job for a new application. The member who sent the offer is told.
 *
 * An accepted offer hires without a dialog: the join date is the start date of the offer (never refused for the date window of a recruiter's hire) and the note is
 * the company's default "hired" text, if it has one.
 */
final readonly class RespondToOffer
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private OfferRepository $offers,
        private StageRepository $stages,
        private OfferViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
        private CompanyMessageTexts $texts,
        private WatchJobFill $fill,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-offer`, `offer-expired` or `applications-unavailable`
     */
    public function accept(Actor $actor, int $applicationId): OfferView
    {
        return $this->apply($actor, $applicationId, OfferChange::Accepted, StageType::Hired, null);
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-offer`, `offer-expired` or `applications-unavailable`
     * @throws BusinessRuleViolation `invalid-answer`
     */
    public function decline(Actor $actor, int $applicationId, ?string $note, ?string $reason = null): OfferView
    {
        $note = $note === null ? null : trim($note);

        if ($note !== null && mb_strlen($note) > Offer::ANSWER_NOTE_MAX) {
            $message = 'The note can have at most '.Offer::ANSWER_NOTE_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-answer', extensions: ['errors' => ['note' => [$message]]]);
        }

        $withdrawalReason = WithdrawalReason::fromRequest($reason, byTeam: false, code: 'invalid-answer');

        return $this->apply($actor, $applicationId, OfferChange::Declined, StageType::Withdrawn, $note === '' ? null : $note, $withdrawalReason);
    }

    private function apply(Actor $actor, int $applicationId, OfferChange $answer, StageType $target, ?string $note, ?WithdrawalReason $reason = null): OfferView
    {
        $userId = $this->guard->userId($actor);

        [$saved, $events] = $this->transactions->run(function () use ($userId, $applicationId, $answer, $target, $note, $reason): array {
            $application = $this->applications->findOfApplicant($userId, $applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $offer = $this->offers->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no pending offer.', 'no-active-offer');
            $now = $this->clock->now();
            $stage = $this->defaultStage($application->companyId, $target) ?? throw new StateConflict('This company cannot take applications right now.', 'applications-unavailable');

            $saved = $answer === OfferChange::Accepted ? $offer->accept($now) : $offer->decline($note, $now);
            $this->offers->update($saved);

            $moved = $answer === OfferChange::Accepted ? $application->moveTo((int) $stage->id, StageType::Hired) : $application->withdraw((int) $stage->id, $note);

            $hire = $answer === OfferChange::Accepted ? new HireDetails($offer->startDate, $this->texts->hired($application->companyId)) : null;

            $filled = null;

            if ($answer === OfferChange::Accepted) {
                $this->applications->recordHire($application, $moved, $now, $hire);
                $filled = $this->fill->afterMove($application, $moved, $now);
            } else {
                $this->applications->recordWithdrawal($application, $moved, $now, $reason);
            }

            return [$saved, [
                new ApplicationStageChanged($applicationId, $application->jobId, $application->companyId, $userId, $application->stageType, $moved->stageType, $now, $hire),
                OfferChanged::of($answer, $application, $saved, $now),
                ...($filled instanceof JobFilled ? [$filled] : []),
                ...($answer === OfferChange::Declined ? [new ApplicationWithdrawn($applicationId, $application->jobId, $application->companyId, $userId, StageMoveActor::Applicant, $reason, null, [$offer->createdBy], null, null, null, $now)] : []),
            ]];
        });

        $this->events->dispatch($events);

        return $this->views->one($saved);
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
}
