<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds an interview for the member who may see its application: a foreign or unassigned interview is a plain 404. */
final readonly class TeamInterviewLookup
{
    public function __construct(private RecruitmentAccess $access, private TeamApplicationLookup $applications, private InterviewRepository $interviews) {}

    /**
     * @param  ?CompanyPermission  $permission  what the action needs besides reading (`applications.view`); null for a read
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $interviewId, bool $forUpdate = false, ?CompanyPermission $permission = null): TeamInterview
    {
        $interview = $this->interviews->findById($interviewId);

        if (! $interview instanceof Interview) {
            // The caller is still checked as a member first, so the answer does not depend on the account kind.
            $this->access->viewer($actor, $permission);

            throw EntityNotFound::of('Interview', $interviewId);
        }

        $team = $this->applications->find($actor, $interview->applicationId, $forUpdate, $permission);

        return new TeamInterview($team->member, $interview, $team->application);
    }
}
