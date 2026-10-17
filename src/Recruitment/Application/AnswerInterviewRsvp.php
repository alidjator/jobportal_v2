<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\InterviewPanelAnswered;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Rsvp;
use Src\Recruitment\Domain\RsvpAnswer;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The answer of a panel member (the main interviewer or an extra member) to the invitation to an interview (`docs/design/phase-8-interviewers.md` section 2): "Hadir"
 * or "Tidak bisa", with an optional note, changeable until the interview starts. "Tidak bisa" tells the member who scheduled it; the panel stays as it is.
 */
final readonly class AnswerInterviewRsvp
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private ScorecardAccess $access,
        private InterviewRsvps $rsvps,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `not-on-panel`
     * @throws EntityNotFound
     * @throws StateConflict `application-final`, `interview-not-active` or `interview-started`
     */
    public function execute(Actor $actor, int $interviewId, Rsvp $answer, ?string $note): RsvpAnswer
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);

        [$saved, $event] = $this->transactions->run(function () use ($actor, $interviewId, $answer, $note): array {
            $team = $this->lookup->find($actor, $interviewId, forUpdate: true, permission: CompanyPermission::InterviewsManage, panelOpens: true);
            $this->access->assertOnPanel($team);
            $interview = $team->interview;
            $type = $team->application->stageType;

            if ($type->isTerminal() || $type === StageType::Hired) {
                throw new StateConflict('This application is final, so the interview needs no answer.', 'application-final');
            }

            if (! in_array($interview->status, [InterviewStatus::Pending, InterviewStatus::Confirmed], true)) {
                throw new StateConflict('This interview is no longer active.', 'interview-not-active');
            }

            $now = $this->clock->now();

            if ($now >= $interview->startsAt) {
                throw new StateConflict('This interview has started, so the answer can no longer change.', 'interview-started');
            }

            $this->rsvps->answer((int) $interview->id, $team->member->userId, $answer, $note, $now);

            return [
                new RsvpAnswer($answer, $note, $now),
                $answer === Rsvp::Declined
                    ? new InterviewPanelAnswered((int) $interview->id, $interview->applicationId, $team->application->jobId, $team->application->companyId, $team->member->userId, $interview->createdBy, $note, $now)
                    : null,
            ];
        });

        if ($event instanceof InterviewPanelAnswered) {
            $this->events->dispatch([$event]);
        }

        return $saved;
    }
}
