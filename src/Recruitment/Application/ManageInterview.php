<?php

namespace Src\Recruitment\Application;

use Closure;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What the team does with the active interview of an application: accept the time the job seeker proposed, cancel it, or mark it completed. All lock the
 * application row first. Accepting and cancelling tell the job seeker.
 */
final readonly class ManageInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewRepository $interviews,
        private InterviewViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `no-proposal`
     */
    public function acceptProposal(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::ProposalAccepted, static fn (Interview $interview): Interview => $interview->acceptProposal());
    }

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`
     */
    public function cancel(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, InterviewChange::Cancelled, static fn (Interview $interview): Interview => $interview->cancel());
    }

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `interview-not-confirmed`
     */
    public function complete(Actor $actor, int $applicationId): InterviewView
    {
        return $this->apply($actor, $applicationId, null, static fn (Interview $interview): Interview => $interview->complete());
    }

    /** @param  Closure(Interview): Interview  $change */
    private function apply(Actor $actor, int $applicationId, ?InterviewChange $announce, Closure $change): InterviewView
    {
        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId, $announce, $change): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true);
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
