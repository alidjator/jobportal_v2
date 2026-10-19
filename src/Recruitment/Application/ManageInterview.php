<?php

namespace Src\Recruitment\Application;

use Closure;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What the team does with the active interview of an application: accept or refuse the time the job seeker proposed, cancel it, change its note, mark it completed
 * or record a no-show (and correct a mistaken no-show by marking it completed). All lock the application row first. Accepting, refusing, cancelling and a no-show
 * tell the job seeker; accepting moves the time in place, so the panel is invited again and its answers go back to `pending`.
 */
final readonly class ManageInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewRepository $interviews,
        private InterviewViews $views,
        private InterviewRsvps $rsvps,
        private CandidateMessageRules $messageRules,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `no-proposal`
     */
    public function acceptProposal(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::ProposalAccepted, function (Interview $interview): Interview {
            $accepted = $interview->acceptProposal();
            $this->rsvps->resetAll((int) $interview->id);

            return $accepted;
        });
    }

    /**
     * Keeps the current time: the proposal is cleared and the job seeker gets the optional message, which is not stored.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `no-proposal`
     * @throws BusinessRuleViolation `message-link-not-allowed`
     */
    public function refuseProposal(Actor $actor, int $applicationId, ?string $message): InterviewView
    {
        $message = $message === null || trim($message) === '' ? null : trim($message);

        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId, $message): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');
            $refused = $interview->refuseProposal();
            $this->messageRules->assertAllowed($team->application->companyId, $message, 'message');
            $this->interviews->update($refused);

            return [$refused, InterviewChanged::of(InterviewChange::ProposalRefused, $team->application, $refused, $this->clock->now(), message: $message)];
        });

        $this->events->dispatch([$event]);

        return $this->views->one($saved, withPanel: true);
    }

    /**
     * Records that the job seeker did not come to the confirmed interview after its start. Allowed with `interviews.manage` or to the main interviewer.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `interview-not-confirmed` or `interview-not-started`
     * @throws BusinessRuleViolation `invalid-interview` when the note is too long
     */
    public function markNoShow(Actor $actor, int $applicationId, ?string $note): InterviewView
    {
        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId, $note): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, panelOpens: true);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');

            if ($team->member->userId !== $interview->interviewerId) {
                $team->member->membership->require(CompanyPermission::InterviewsManage);
            }

            $now = $this->clock->now();
            $marked = $interview->markNoShow($note, $now);
            $this->interviews->update($marked);

            return [$marked, InterviewChanged::of(InterviewChange::NoShow, $team->application, $marked, $now)];
        });

        $this->events->dispatch([$event]);

        return $this->views->one($saved, withPanel: true);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`
     */
    public function cancel(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::Cancelled, static fn (Interview $interview): Interview => $interview->cancel());
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `interview-not-confirmed`
     */
    public function complete(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, null, static fn (Interview $interview): Interview => $interview->complete(), correctsNoShow: true);
    }

    /**
     * Changes only the note for the candidate of the active interview (pending or confirmed): the status, the time and the proposals stay. When the note
     * really changed, the job seeker is told the details changed and gets the event again with a higher sequence; nothing changed, nothing sent.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `interview-not-active`
     * @throws BusinessRuleViolation `invalid-interview` when the note is too long, or `message-link-not-allowed`
     */
    public function updateNote(Actor $actor, int $applicationId, ?string $note): InterviewView
    {
        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId, $note): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This interview is no longer active.', 'interview-not-active');
            $this->messageRules->assertAllowed($team->application->companyId, $note);
            $changed = $interview->withNote($note);

            if ($changed->note === $interview->note) {
                return [$interview, null];
            }

            $this->interviews->update($changed);

            return [$changed, InterviewChanged::of(InterviewChange::Updated, $team->application, $changed, $this->clock->now())];
        });

        if ($event instanceof InterviewChanged) {
            $this->events->dispatch([$event]);
        }

        return $this->views->one($saved, withPanel: true);
    }

    /**
     * @param  Closure(Interview): Interview  $change
     * @param  bool  $correctsNoShow  whether the newest interview may also be a no-show (not active) when the application has no active one
     */
    private function apply(Actor $actor, int $applicationId, ?InterviewChange $announce, Closure $change, bool $correctsNoShow = false): InterviewView
    {
        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId, $announce, $change, $correctsNoShow): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? ($correctsNoShow ? $this->noShow($applicationId) : null)
                ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');
            $saved = $change($interview);
            $this->interviews->update($saved);

            return [$saved, $announce === null ? null : InterviewChanged::of($announce, $team->application, $saved, $this->clock->now())];
        });

        if ($event instanceof InterviewChanged) {
            $this->events->dispatch([$event]);
        }

        return $this->views->one($saved, withPanel: true);
    }

    /** The newest interview of the application when it is a no-show, locked. */
    private function noShow(int $applicationId): ?Interview
    {
        $latest = $this->interviews->latest($applicationId, forUpdate: true);

        return $latest !== null && $latest->status === InterviewStatus::NoShow ? $latest : null;
    }
}
