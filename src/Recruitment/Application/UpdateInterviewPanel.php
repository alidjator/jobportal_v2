<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Replaces the extra members of the panel of an existing interview (the main interviewer stays), for the member who scheduled it, its main interviewer or a
 * company admin, until the application is final. A member who leaves loses their draft scorecard; a scorecard they already sent stays and counts in the result.
 * A member who joins must send their own scorecard before seeing the others, and is invited by a notice and an email with the calendar file; a member who leaves gets the cancellation.
 */
final readonly class UpdateInterviewPanel
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private InterviewScheduler $scheduler,
        private InterviewPanel $panel,
        private HiringManagerVisibility $visibility,
        private ScorecardRepository $scorecards,
        private InterviewViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $participantIds
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `interview-edit-denied`
     * @throws EntityNotFound
     * @throws StateConflict `interview-not-active` or `application-final`
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    public function execute(Actor $actor, int $interviewId, array $participantIds): InterviewView
    {
        [$team, $event] = $this->transactions->run(function () use ($actor, $interviewId, $participantIds): array {
            $team = $this->lookup->find($actor, $interviewId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $team->interview;

            if (! $team->member->isCompanyAdmin() && $team->member->userId !== $interview->createdBy && $team->member->userId !== $interview->interviewerId) {
                throw new AccessDenied('Only the member who scheduled the interview, its interviewer or a company admin can change its panel.', 'interview-edit-denied');
            }

            $type = $team->application->stageType;

            if ($type->isTerminal() || $type === StageType::Hired) {
                throw new StateConflict('This application is final, so the panel can no longer change.', 'application-final');
            }

            if (in_array($interview->status, [InterviewStatus::Declined, InterviewStatus::Cancelled], true)) {
                throw new StateConflict('This interview is no longer active.', 'interview-not-active');
            }

            $ids = $this->scheduler->checkedParticipants($team->application->companyId, $interview->interviewerId, $participantIds);
            $current = $this->panel->forInterviews([(int) $interview->id])[(int) $interview->id] ?? [];
            $left = array_values(array_diff($current, $ids));
            $added = array_values(array_diff($ids, $current));

            $this->scorecards->deleteDrafts((int) $interview->id, $left);
            $this->panel->replace((int) $interview->id, $ids);
            $now = $this->clock->now();
            $this->visibility->showToPanel((int) $team->application->id, $team->application->jobId, $ids, $now);

            return [$team, $left === [] && $added === [] ? null : InterviewChanged::of(InterviewChange::PanelChanged, $team->application, $interview, $now, $added, $left)];
        });

        if ($event instanceof InterviewChanged) {
            $this->events->dispatch([$event]);
        }

        return $this->views->one($team->interview, withPanel: true);
    }
}
