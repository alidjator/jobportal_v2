<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The rules about who scores an interview and who sees the scores. The panel is the main interviewer plus the extra members. Scoring is open on a confirmed
 * or completed interview from its start, until the application is final. A panel member sees the other scorecards only after sending their own.
 */
final readonly class ScorecardAccess
{
    public function __construct(private InterviewPanel $panel, private ScorecardRepository $scorecards) {}

    /** @return list<int> the main interviewer first */
    public function panel(Interview $interview): array
    {
        return [$interview->interviewerId, ...($this->panel->forInterviews([(int) $interview->id])[(int) $interview->id] ?? [])];
    }

    /** @throws AccessDenied `not-on-panel` */
    public function assertOnPanel(TeamInterview $team): void
    {
        if (! in_array($team->member->userId, $this->panel($team->interview), true)) {
            throw new AccessDenied('Only the members of the interview panel can fill a scorecard.', 'not-on-panel');
        }
    }

    /**
     * @throws StateConflict `application-final` or `scoring-not-open`
     */
    public function assertOpen(TeamInterview $team, DateTimeImmutable $now): void
    {
        $type = $team->application->stageType;

        if ($type->isTerminal() || $type === StageType::Hired) {
            throw new StateConflict('This application is final, so its scorecards can no longer change.', 'application-final');
        }

        $status = $team->interview->status;

        if (($status !== InterviewStatus::Confirmed && $status !== InterviewStatus::Completed) || $now < $team->interview->startsAt) {
            throw new StateConflict('A scorecard can be filled from the start of a confirmed interview.', 'scoring-not-open');
        }
    }

    /**
     * Whether the member may read the sent scorecards of the interview: anyone who sees the application, except a panel member who has not sent their own.
     */
    public function mayReadSent(TeamInterview $team): bool
    {
        return ! in_array($team->member->userId, $this->panel($team->interview), true)
            || in_array($team->member->userId, $this->scorecards->sentBy((int) $team->interview->id), true);
    }

    /** @throws AccessDenied `scorecards-locked` */
    public function assertMayReadSent(TeamInterview $team): void
    {
        if (! $this->mayReadSent($team)) {
            throw new AccessDenied('Send your own scorecard first: the other scorecards stay hidden until then.', 'scorecards-locked');
        }
    }
}
