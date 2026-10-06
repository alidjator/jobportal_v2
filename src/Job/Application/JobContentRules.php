<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobContent;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What a job may hold, as far as the outside world is concerned: every master id exists and is active, the location is one matching chain, and the
 * salary is consistent. Only what changed is checked against the masters (an item an admin deactivated later does not block unrelated edits), and when any
 * location level changes the whole chain is checked together with the levels that did not change. This is the validity of a draft; completeness is
 * checked when the job is published.
 */
final readonly class JobContentRules
{
    /** API field => [content property, master URL name]. */
    private const array SINGLE_MASTERS = [
        'category_id' => ['categoryId', 'job-categories'],
        'role_id' => ['roleId', 'job-roles'],
        'job_type_id' => ['jobTypeId', 'job-types'],
        'job_level_id' => ['jobLevelId', 'job-levels'],
        'work_time_id' => ['workTimeId', 'work-times'],
        'experience_level_id' => ['experienceLevelId', 'experience-levels'],
        'education_level_id' => ['educationLevelId', 'education-levels'],
        'salary_period_id' => ['salaryPeriodId', 'salary-periods'],
    ];

    private const array LOCATION_PROPERTIES = ['provinceId', 'cityId', 'subdistrictId', 'villageId', 'postalCodeId'];

    public function __construct(
        private ReferenceValues $references,
        private LocationReferences $locations,
    ) {}

    /**
     * @param  ?JobContent  $before  the content before the change, or null for a new job (everything counts as changed)
     * @param  array<string, list<string>>  $errors  problems found earlier (such as bad tags), reported together with the ones found here
     *
     * @throws BusinessRuleViolation `invalid-job` with `errors` per field
     */
    public function check(JobContent $content, ?JobContent $before, array $errors = []): void
    {
        foreach (self::SINGLE_MASTERS as $field => [$property, $master]) {
            $id = $content->{$property};

            if ($id !== null && ($before === null || $before->{$property} !== $id) && ! $this->references->isActive($master, $id)) {
                $errors[$field][] = 'The value does not exist or is not active.';
            }
        }

        $this->checkList($errors, 'skill_ids', 'skills', $content->skillIds, $before === null ? [] : $before->skillIds);
        $this->checkList($errors, 'benefit_ids', 'benefits', $content->benefitIds, $before === null ? [] : $before->benefitIds);

        if ($content->hasSalaryAmount() && $content->salaryPeriodId === null) {
            $errors['salary_period_id'][] = 'A salary period is required when a salary amount is given.';
        }

        if ($content->salaryMin !== null && $content->salaryMax !== null && $content->salaryMin > $content->salaryMax) {
            $errors['salary_min'][] = 'The minimum salary cannot be above the maximum salary.';
        }

        if ($this->locationTouched($content, $before)
            && ! $this->locations->isValidChain($content->provinceId, $content->cityId, $content->subdistrictId, $content->villageId, $content->postalCodeId)) {
            $errors['location'][] = 'The location is not a matching chain: each level must belong to the one above it, and a level needs the levels above it.';
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The job is not valid.', 'invalid-job', extensions: ['errors' => $errors]);
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<int>  $ids
     * @param  list<int>  $earlier
     */
    private function checkList(array &$errors, string $field, string $master, array $ids, array $earlier): void
    {
        foreach ($ids as $id) {
            if (! in_array($id, $earlier, true) && ! $this->references->isActive($master, $id)) {
                $errors[$field][] = "The item {$id} does not exist or is not active.";
            }
        }
    }

    private function locationTouched(JobContent $content, ?JobContent $before): bool
    {
        if ($before === null) {
            return true;
        }

        foreach (self::LOCATION_PROPERTIES as $property) {
            if ($content->{$property} !== $before->{$property}) {
                return true;
            }
        }

        return false;
    }
}
