<?php

namespace Src\Recruitment\Application;

use Closure;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
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
 * What the team does with the active interview of an application: accept the time the job seeker proposed, cancel it, change its note, or mark it completed. All lock the
 * application row first. Accepting and cancelling tell the job seeker.
 */
final readonly class ManageInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewRepository $interviews,
        private InterviewViews $views,
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
        return $this->apply($actor, $applicationId, InterviewChange::ProposalAccepted, static fn (Interview $interview): Interview => $interview->acceptProposal());
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
        return $this->apply($actor, $applicationId, null, static fn (Interview $interview): Interview => $interview->complete());
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

    /** @param  Closure(Interview): Interview  $change */
    private function apply(Actor $actor, int $applicationId, ?InterviewChange $announce, Closure $change): InterviewView
    {
        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId, $announce, $change): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');
            $saved = $change($interview);
            $this->interviews->update($saved);

            return [$saved, $announce === null ? null : InterviewChanged::of($announce, $team->application, $saved, $this->clock->now())];
        });

        if ($event instanceof InterviewChanged) {
            $this->events->dispatch([$event]);
        }

        return $this->views->one($saved, withPanel: true);
    }
}
