<?php

namespace Src\DemoData\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Company\Domain\MemberPreset;
use Src\Job\Application\Admin\ApproveJob;
use Src\Job\Application\CreateJob;
use Src\Job\Application\GetJob;
use Src\Job\Application\PublishJob;
use Src\Job\Application\ReplaceJobAssignees;
use Src\Job\Domain\JobAssignmentRole;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;

/**
 * The jobs of both companies. The recruiter writes the jobs of the verified company and its company admin assigns the recruiter and the hiring manager to
 * them; the company admin of the other company writes its own. Every job but the draft is published, and the platform admin approves a job that went to
 * review (the jobs of the unverified company do, under the default settings). Closing a job waits for its applications (`DemoApplications`).
 */
final readonly class DemoJobs
{
    private const int DEADLINE_DAYS = 30;

    public function __construct(
        private CreateJob $createJob,
        private PublishJob $publishJob,
        private ReplaceJobAssignees $replaceAssignees,
        private GetJob $getJob,
        private ApproveJob $approveJob,
        private DemoReferences $references,
        private DemoActors $actors,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams
     * @return array<string, int> the job ids by key
     */
    public function build(Actor $admin, array $teams): array
    {
        $sinar = $teams[DemoCast::SINAR]['members'];
        $content = $this->sharedContent();
        $ids = [];

        foreach (DemoCast::jobs() as $key => $job) {
            $team = $teams[$job['company']];
            $author = $this->actors->member($job['company'] === DemoCast::SINAR ? $sinar[MemberPreset::Recruiter->value] : $team['admin_id']);

            $id = $this->createJob->execute($author, $job['title'], [
                ...$content,
                'description' => $job['description'],
                'workMode' => $job['work_mode'],
                'salaryMin' => $job['salary'][0],
                'salaryMax' => $job['salary'][1],
                'vacancies' => $job['vacancies'],
            ]);

            if ($job['company'] === DemoCast::SINAR) {
                $this->replaceAssignees->execute($this->actors->member($team['admin_id']), $id, [
                    $sinar[MemberPreset::Recruiter->value] => JobAssignmentRole::Recruiter,
                    $sinar[MemberPreset::HiringManager->value] => JobAssignmentRole::HiringManager,
                ], null, DemoCast::USER_AGENT);
            }

            if ($job['state'] !== 'draft') {
                $this->publishJob->execute($author, $id, null, DemoCast::USER_AGENT);

                if ($this->getJob->execute($author, $id, DemoCast::LANGUAGE)->job->status === JobStatus::PendingReview) {
                    $this->approveJob->execute($admin, $id, null, DemoCast::USER_AGENT);
                }
            }

            $ids[$key] = $id;
        }

        return $ids;
    }

    /** @return array<string, mixed> the content properties every demo job shares */
    private function sharedContent(): array
    {
        $jakarta = new DateTimeZone('Asia/Jakarta');
        $today = $this->clock->now()->setTimezone($jakarta)->format('Y-m-d');

        return [
            'categoryId' => $this->references->id('job-categories'),
            'roleId' => $this->references->id('job-roles'),
            'jobTypeId' => $this->references->id('job-types'),
            'jobLevelId' => $this->references->id('job-levels'),
            'workTimeId' => $this->references->id('work-times'),
            'experienceLevelId' => $this->references->id('experience-levels'),
            'educationLevelId' => $this->references->id('education-levels'),
            'salaryPeriodId' => $this->references->id('salary-periods'),
            'salaryVisible' => true,
            'skillIds' => $this->references->ids('skills', 3),
            'deadline' => (new DateTimeImmutable($today, new DateTimeZone('UTC')))->modify('+'.self::DEADLINE_DAYS.' days'),
            ...$this->references->place(),
        ];
    }
}
