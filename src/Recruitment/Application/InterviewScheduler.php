<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Puts an interview on an application: checks the interviewer, cancels the interview that was active (a new time replaces it) and saves the new one. It runs
 * inside the caller's transaction, with the application row already locked.
 */
final readonly class InterviewScheduler
{
    public function __construct(
        private InterviewRepository $interviews,
        private CompanyTeam $team,
        private JobDirectory $jobs,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-interview` or `invalid-interviewer`
     */
    public function schedule(RecruitmentMember $member, Application $application, InterviewInput $input): InterviewChanged
    {
        $now = $this->clock->now();
        $applicationId = (int) $application->id;
        $this->assertInterviewer($application, $input->interviewerId);

        $interview = Interview::schedule(
            $applicationId,
            $input->startsAt,
            $input->durationMinutes,
            $input->format,
            $input->location,
            $input->meetingUrl,
            $input->interviewerId,
            $member->userId,
            $now,
        );

        $previous = $this->interviews->active($applicationId, forUpdate: true);

        if ($previous instanceof Interview) {
            $this->interviews->update($previous->cancel());
        }

        $saved = $this->interviews->add($interview);

        return InterviewChanged::of($previous instanceof Interview ? InterviewChange::Rescheduled : InterviewChange::Scheduled, $application, $saved, $now);
    }

    /** The interviewer is a member of the company who may see the job (an admin, or a recruiter the job is assigned to). */
    private function assertInterviewer(Application $application, int $interviewerId): void
    {
        $role = $this->team->roles($application->companyId)[$interviewerId] ?? null;

        if ($role === null || $this->jobs->visibleToMember($application->jobId, $application->companyId, $interviewerId, $role === 'company_admin') === null) {
            $message = 'Choose a member of your team who works on this job.';

            throw new BusinessRuleViolation($message, 'invalid-interviewer', extensions: ['errors' => ['interviewer_id' => [$message]]]);
        }
    }
}
