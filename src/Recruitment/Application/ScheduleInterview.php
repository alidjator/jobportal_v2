<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Schedules the interview of an application that is in an interview stage, or replaces the active one with a new time. The job seeker is told. */
final readonly class ScheduleInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewScheduler $scheduler,
        private InterviewRepository $interviews,
        private InterviewViews $views,
        private JobDirectory $jobs,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @return array{view: InterviewView, replaced: bool}
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `job-taken-down` or `not-in-interview-stage`
     * @throws BusinessRuleViolation `invalid-interview` or `invalid-interviewer`
     */
    public function execute(Actor $actor, int $applicationId, InterviewInput $input): array
    {
        [$event, $interviewId] = $this->transactions->run(function () use ($actor, $applicationId, $input): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true);

            if ($this->jobs->isTakenDown($team->application->jobId)) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            if ($team->application->stageType !== StageType::Interview) {
                throw new StateConflict('Move the application to an interview stage before scheduling an interview.', 'not-in-interview-stage');
            }

            $event = $this->scheduler->schedule($team->member, $team->application, $input);

            return [$event, $event->interviewId];
        });

        $this->events->dispatch([$event]);

        return [
            'view' => $this->views->one($this->interviews->findById($interviewId) ?? throw EntityNotFound::of('Interview', $interviewId), withPanel: true),
            'replaced' => $event->change === InterviewChange::Rescheduled,
        ];
    }
}
