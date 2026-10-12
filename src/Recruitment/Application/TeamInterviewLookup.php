<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds an interview for the member who may see its application: a foreign or unassigned interview is a plain 404. */
final readonly class TeamInterviewLookup
{
    public function __construct(private RecruitmentAccess $access, private TeamApplicationLookup $applications, private InterviewRepository $interviews) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $interviewId): TeamInterview
    {
        $interview = $this->interviews->findById($interviewId);

        if (! $interview instanceof Interview) {
            // The caller is still checked as a member first, so the answer does not depend on the account kind.
            $this->access->member($actor);

            throw EntityNotFound::of('Interview', $interviewId);
        }

        return new TeamInterview($this->applications->find($actor, $interview->applicationId)->member, $interview);
    }
}
