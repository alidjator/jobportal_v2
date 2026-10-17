<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The calendar file of an interview for the team member who may see its application (`METHOD:PUBLISH`, without the attendee). A foreign or unassigned interview is a plain 404. */
final readonly class GetInterviewCalendarFile
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private InterviewCalendar $calendar,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): string
    {
        $team = $this->lookup->find($actor, $interviewId);
        $jobId = $team->application->jobId;
        $company = $this->companies->summaries([$team->application->companyId])[$team->application->companyId] ?? null;

        return $this->calendar->write($team->interview, InterviewCalendar::PUBLISH, $this->jobs->titles([$jobId])[$jobId] ?? '', $company === null ? '' : $company->name, null, $this->clock->now());
    }
}
