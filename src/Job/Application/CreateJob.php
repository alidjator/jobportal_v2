<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Application\Workforce\RequisitionGate;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company member starts a job as a draft: only the title is required, and anything else sent is checked for validity (not completeness). The creator
 * is assigned to the job at once, so a recruiter can keep working on what they started. A tenant of an ERP group whose setting asks for it opens a job only for a requisition
 * (`docs/design/erp-requisitions.md` section 1): the requisition must be approved and have hires left, and the job takes the job role of its position.
 */
final readonly class CreateJob
{
    public function __construct(
        private JobAccess $access,
        private JobContentChanges $changes,
        private JobSlugGenerator $slugs,
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private RequisitionGate $requisitions,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $fields  content property changes besides the title (camel case names; `tags` as raw text)
     * @return int the id of the new job
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field, `requisition-required`, `invalid-requisition` or `requisition-vacancies-exceeded`
     * @throws StateConflict `requisition-not-open`
     */
    public function execute(Actor $actor, string $title, array $fields, ?int $requisitionId = null): int
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsCreate);

        if ($requisitionId === null && $this->requisitions->isRequired($member->companyId)) {
            throw new BusinessRuleViolation('This tenant opens its jobs from an approved requisition.', 'requisition-required');
        }

        $content = $this->changes->apply(JobContent::draft($title), $fields, isNew: true, companyId: $member->companyId);

        return $this->transactions->run(function () use ($member, $content, $requisitionId): int {
            if ($requisitionId !== null) {
                $vacancies = $content->vacancies ?? throw new BusinessRuleViolation('A job opened for a requisition names its vacancies.', 'invalid-job', extensions: ['errors' => ['vacancies' => ['The vacancies are required for a job opened for a requisition.']]]);
                $terms = $this->requisitions->claim($member->companyId, $requisitionId, $vacancies);
                $content = $content->roleId === null ? $content->with(['roleId' => $terms->jobRoleId]) : $content;
            }

            $job = $this->jobs->create(Job::draft($member->companyId, $this->slugs->make($content->title), $content, $member->userId, $requisitionId));
            $this->assignments->add((int) $job->id, $member->userId, null, $this->clock->now());

            return (int) $job->id;
        });
    }
}
