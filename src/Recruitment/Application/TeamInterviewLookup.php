<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds an interview for the member who may see its application: a foreign or unassigned interview is a plain 404. */
final readonly class TeamInterviewLookup
{
    public function __construct(private RecruitmentAccess $access, private TeamApplicationLookup $applications, private InterviewRepository $interviews, private InterviewPanel $panel) {}

    /**
     * @param  ?CompanyPermission  $permission  what the action needs besides reading (`applications.view`); null for a read
     * @param  bool  $panelOpens  whether a member on the panel of the interview's application, holding `interviews.participate`, may do it without seeing the job
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $interviewId, bool $forUpdate = false, ?CompanyPermission $permission = null, bool $panelOpens = false): TeamInterview
    {
        $interview = $this->interviews->findById($interviewId);

        if (! $interview instanceof Interview) {
            // The caller is still checked as a member first, so the answer does not depend on the account kind.
            $panelOpens ? $this->access->member($actor) : $this->access->viewer($actor, $permission);

            throw EntityNotFound::of('Interview', $interviewId);
        }

        $team = $this->applications->find($actor, $interview->applicationId, $forUpdate, $permission, $panelOpens);

        return new TeamInterview($team->member, $interview, $team->application, $team->panelOnly);
    }

    /**
     * A member who reached the interview only through a panel sees its recordings and calls only when they sat on this interview's panel (the panel of another
     * interview of the same application is not enough); anyone who sees the job is not restricted.
     *
     * @throws EntityNotFound
     */
    public function assertSatOn(TeamInterview $team): void
    {
        if ($team->panelOnly && ! in_array($team->member->userId, [$team->interview->interviewerId, ...($this->panel->forInterviews([(int) $team->interview->id])[(int) $team->interview->id] ?? [])], true)) {
            throw EntityNotFound::of('Interview', (int) $team->interview->id);
        }
    }
}
