<?php

namespace Src\JobAlert\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What an alert looks for: the filters of the public job search, by their names: `q` (a keyword of up to 5 words), `work_mode`, the id filters (each a list
 * of up to 20 ids, matching any) and `salary_min` with `salary_period_id`. Whatever is empty is dropped, so equal searches are stored the same way. At least
 * one filter is required (an empty alert would match every job) and a minimum salary needs its period.
 */
final readonly class AlertCriteria
{
    public const int MAX_KEYWORD_LENGTH = 100;

    public const int MAX_KEYWORD_WORDS = 5;

    public const int MAX_VALUES = 20;

    public const array ID_FILTERS = [
        'category_id', 'role_id', 'job_type_id', 'job_level_id', 'experience_level_id', 'education_level_id', 'work_time_id', 'province_id', 'city_id', 'skill_id', 'company_id',
    ];

    public const array WORK_MODES = ['onsite', 'hybrid', 'remote'];

    public const int MAX_SALARY = 9999999999;

    /** @param  array<string, mixed>  $filters  already cleaned, see `of` */
    private function __construct(private array $filters) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws BusinessRuleViolation `criteria-required`, `salary-period-required` or `invalid-criteria`
     */
    public static function of(array $input): self
    {
        $filters = [];

        $keyword = is_string($input['q'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $input['q'])) : '';

        if ($keyword !== '') {
            if (mb_strlen($keyword) > self::MAX_KEYWORD_LENGTH || count(explode(' ', $keyword)) > self::MAX_KEYWORD_WORDS) {
                throw new BusinessRuleViolation('The keyword can have at most '.self::MAX_KEYWORD_WORDS.' words and '.self::MAX_KEYWORD_LENGTH.' characters.', 'invalid-criteria');
            }

            $filters['q'] = $keyword;
        }

        foreach (self::ID_FILTERS as $name) {
            $ids = self::ids($input[$name] ?? [], $name);

            if ($ids !== []) {
                $filters[$name] = $ids;
            }
        }

        $modes = self::list($input['work_mode'] ?? [], 'work_mode');

        foreach ($modes as $mode) {
            if (! is_string($mode) || ! in_array($mode, self::WORK_MODES, true)) {
                throw new BusinessRuleViolation('The work mode must be one of: '.implode(', ', self::WORK_MODES).'.', 'invalid-criteria');
            }
        }

        if ($modes !== []) {
            $filters['work_mode'] = array_values(array_unique($modes));
        }

        if (($input['salary_min'] ?? null) !== null) {
            $salary = $input['salary_min'];

            if (! is_int($salary) || $salary < 0 || $salary > self::MAX_SALARY) {
                throw new BusinessRuleViolation('The minimum salary must be a whole number from 0 to '.self::MAX_SALARY.'.', 'invalid-criteria');
            }

            $period = $input['salary_period_id'] ?? null;

            if ($period === null) {
                throw new BusinessRuleViolation('A minimum salary needs the salary period it is meant in.', 'salary-period-required');
            }

            if (! is_int($period) || $period < 1) {
                throw new BusinessRuleViolation('The salary period must be a positive whole number.', 'invalid-criteria');
            }

            $filters['salary_min'] = $salary;
            $filters['salary_period_id'] = $period;
        }

        if ($filters === []) {
            throw new BusinessRuleViolation('An alert needs at least one filter, such as a keyword, a role or a place.', 'criteria-required');
        }

        return new self($filters);
    }

    /**
     * What was stored before, read back without the rules of a new alert.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function restore(array $filters): self
    {
        return new self($filters);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->filters;
    }

    /** @return list<mixed> */
    private static function list(mixed $value, string $name): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_VALUES) {
            throw new BusinessRuleViolation("{$name} must be a list of up to ".self::MAX_VALUES.' values.', 'invalid-criteria');
        }

        return $value;
    }

    /** @return list<int> */
    private static function ids(mixed $value, string $name): array
    {
        $ids = [];

        foreach (self::list($value, $name) as $id) {
            if (! is_int($id) || $id < 1) {
                throw new BusinessRuleViolation("{$name} must hold positive whole numbers.", 'invalid-criteria');
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }
}
