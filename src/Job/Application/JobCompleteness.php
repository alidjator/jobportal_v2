<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobContent;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Whether a job may be live: every required field is filled (title, description of at least 50 characters, category, role, job type, level, work time,
 * experience, education, vacancies, a deadline, at least one skill, and province and city unless the job is remote), and the deadline lies between today
 * (Jakarta time) and today plus the company's `jobs.max_deadline_days`. The deadline range can be left out for a live job whose other fields change.
 */
final readonly class JobCompleteness
{
    private const int MIN_DESCRIPTION_LENGTH = 50;

    /** API field => content property. */
    private const array REQUIRED_IDS = [
        'category_id' => 'categoryId',
        'role_id' => 'roleId',
        'job_type_id' => 'jobTypeId',
        'job_level_id' => 'jobLevelId',
        'work_time_id' => 'workTimeId',
        'experience_level_id' => 'experienceLevelId',
        'education_level_id' => 'educationLevelId',
        'vacancies' => 'vacancies',
    ];

    public function __construct(
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `job-incomplete` with `errors` per field
     */
    public function assertComplete(JobContent $content, int $companyId, bool $checkDeadlineRange): void
    {
        $errors = [];

        if ($content->description === null || mb_strlen(trim($content->description)) < self::MIN_DESCRIPTION_LENGTH) {
            $errors['description'][] = 'A description of at least '.self::MIN_DESCRIPTION_LENGTH.' characters is required.';
        }

        foreach (self::REQUIRED_IDS as $field => $property) {
            if ($content->{$property} === null) {
                $errors[$field][] = 'This field is required.';
            }
        }

        if ($content->skillIds === []) {
            $errors['skill_ids'][] = 'At least one skill is required.';
        }

        if ($content->workMode !== WorkMode::Remote) {
            foreach (['province_id' => $content->provinceId, 'city_id' => $content->cityId] as $field => $value) {
                if ($value === null) {
                    $errors[$field][] = 'The province and the city are required unless the job is remote.';
                }
            }
        }

        $deadlineProblem = $this->deadlineProblem($content, $companyId, $checkDeadlineRange);

        if ($deadlineProblem !== null) {
            $errors['deadline'][] = $deadlineProblem;
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The job is not complete.', 'job-incomplete', extensions: ['errors' => $errors]);
        }
    }

    private function deadlineProblem(JobContent $content, int $companyId, bool $checkRange): ?string
    {
        if ($content->deadline === null) {
            return 'This field is required.';
        }

        if (! $checkRange) {
            return null;
        }

        $today = JakartaDate::today($this->clock->now());
        $days = $this->settings->integer(JobSettings::MAX_DEADLINE_DAYS, $companyId);

        if ($content->deadline < $today) {
            return 'The deadline cannot be before today (Jakarta time).';
        }

        if ($content->deadline > $today->modify("+{$days} days")) {
            return "The deadline cannot be more than {$days} days from today.";
        }

        return null;
    }
}
