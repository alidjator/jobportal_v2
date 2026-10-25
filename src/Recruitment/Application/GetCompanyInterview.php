<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * One interview for the member who may see its application: the job's team, or a member on the panel of any interview of the application (`interviews.participate`). The
 * panel of the interview comes with it, each member with their answer to the invitation. A foreign or unassigned interview is a plain 404.
 */
final readonly class GetCompanyInterview
{
    public function __construct(private TeamInterviewLookup $lookup, private InterviewViews $views, private JobDirectory $jobs) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): CompanyInterviewDetail
    {
        $team = $this->lookup->find($actor, $interviewId, panelOpens: true);
        $jobId = $team->application->jobId;
        $name = $team->application->profileSnapshot['name'] ?? null;

        return new CompanyInterviewDetail(
            $this->views->one($team->interview, withPanel: true),
            $jobId,
            $this->jobs->titles([$jobId])[$jobId] ?? null,
            is_string($name) && $name !== '' ? $name : null,
        );
    }
}
