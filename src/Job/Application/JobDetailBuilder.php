<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignee;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Recruitment\JobHires;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/** Adds what a client needs to show a job: the names of its classifications, skills and benefits in the request language, the names of its place, its assignees with their name and role, and its number of hires. */
final readonly class JobDetailBuilder
{
    /** API field => [content property, master URL name]. */
    private const array CLASSIFICATIONS = [
        'category' => ['categoryId', 'job-categories'],
        'role' => ['roleId', 'job-roles'],
        'job_type' => ['jobTypeId', 'job-types'],
        'job_level' => ['jobLevelId', 'job-levels'],
        'work_time' => ['workTimeId', 'work-times'],
        'experience_level' => ['experienceLevelId', 'experience-levels'],
        'education_level' => ['educationLevelId', 'education-levels'],
        'salary_period' => ['salaryPeriodId', 'salary-periods'],
    ];

    public function __construct(
        private ReferenceValues $references,
        private LocationReferences $locations,
        private JobAssignmentRepository $assignments,
        private CompanyTeam $team,
        private CompanyMemberAccounts $accounts,
        private JobHires $hires,
    ) {}

    public function build(Job $job, string $language): JobDetail
    {
        $content = $job->content;
        $names = [];

        foreach (self::CLASSIFICATIONS as $field => [$property, $master]) {
            $id = $content->{$property};
            $names[$field] = $id === null ? null : $this->references->name($master, $id, $language);
        }

        return new JobDetail(
            $job,
            $this->named('skills', $content->skillIds, $language),
            $this->named('benefits', $content->benefitIds, $language),
            $this->assignees($job),
            $this->hires->countPerJob([(int) $job->id])[(int) $job->id] ?? 0,
            $names,
            $this->locations->names($content->provinceId, $content->cityId, $content->subdistrictId, $content->villageId, $content->postalCodeId),
        );
    }

    /** @return list<array{user_id: int, name: string, role: string, job_role: string}> `role` is the role in the company, `job_role` the role on this job */
    public function assignees(Job $job): array
    {
        $assignees = $this->assignments->assignees((int) $job->id);
        $userIds = array_map(static fn (JobAssignee $assignee): int => $assignee->userId, $assignees);
        $roles = $this->team->roles($job->companyId);
        $accounts = $this->accounts->summaries($userIds);

        return array_map(
            static fn (JobAssignee $assignee): array => [
                'user_id' => $assignee->userId,
                'name' => isset($accounts[$assignee->userId]) ? $accounts[$assignee->userId]->name : '',
                'role' => $roles[$assignee->userId] ?? '',
                'job_role' => $assignee->role->value,
            ],
            $assignees,
        );
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string}>
     */
    private function named(string $master, array $ids, string $language): array
    {
        return array_map(
            fn (int $id): array => ['id' => $id, 'name' => (string) $this->references->name($master, $id, $language)],
            $ids,
        );
    }
}
