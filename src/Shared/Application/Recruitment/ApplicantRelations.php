<?php

namespace Src\Shared\Application\Recruitment;

/**
 * Lets other contexts ask which job seekers have applied to a company and which of them a member may see, without reading Recruitment's tables.
 * A company admin sees every applicant of the company; a recruiter sees the applicants of the jobs assigned to them. Provided by Recruitment.
 */
interface ApplicantRelations
{
    /** Whether the job seeker has any application with the company, whatever its status (a withdrawn one counts). */
    public function hasApplied(int $companyId, int $applicantUserId): bool;

    /** Whether the member may see at least one application of the job seeker (an admin: any application of the company). */
    public function canSeeApplicant(int $companyId, int $memberUserId, bool $isCompanyAdmin, int $applicantUserId): bool;

    /**
     * The job seekers with an application on a job assigned to the member (for a company admin, use `hasApplied` instead: they see everyone).
     *
     * @return list<int>
     */
    public function applicantsVisibleToRecruiter(int $companyId, int $memberUserId): array;

    /**
     * The job seeker of each of these applications that belong to the job; an id that is not an application of that job is missing.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, int> application id => job seeker id
     */
    public function applicantsOfApplications(int $jobId, array $applicationIds): array;

    /**
     * The members assigned to the job of the job seeker's latest application with the company: who to tell when nobody wrote in the thread yet.
     *
     * @return list<int>
     */
    public function assigneesOfLatestApplication(int $companyId, int $applicantUserId): array;
}
