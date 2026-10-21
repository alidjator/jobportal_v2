<?php

namespace Src\JobAlert\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What an alert looks for: the filters of the public job search, by their names: `q` (a keyword of up to 5 words), `work_mode`, the id filters (each a list
 * of up to 20 ids, matching any) `salary_min` with `salary_period_id` and `posted_within` (1, 3, 7 or 30 days; not used by an immediate alert, which is about one job that just went live). Whatever is empty is dropped, so equal searches are stored the same way. At least
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

    public const array POSTED_WITHIN = [1, 3, 7, 30];

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
                throw new BusinessRuleViolation(
                    'The keyword can have at most :words words and :characters characters.', 'invalid-criteria',
                    parameters: ['words' => self::MAX_KEYWORD_WORDS, 'characters' => self::MAX_KEYWORD_LENGTH],
                );
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
                throw new BusinessRuleViolation('The work mode must be one of: :modes.', 'invalid-criteria', parameters: ['modes' => implode(', ', self::WORK_MODES)]);
            }
        }

        if ($modes !== []) {
            $filters['work_mode'] = array_values(array_unique($modes));
        }

        if (($input['salary_min'] ?? null) !== null) {
            $salary = $input['salary_min'];

            if (! is_int($salary) || $salary < 0 || $salary > self::MAX_SALARY) {
                throw new BusinessRuleViolation('The minimum salary must be a whole number from 0 to :max.', 'invalid-criteria', parameters: ['max' => self::MAX_SALARY]);
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

        if (($input['posted_within'] ?? null) !== null) {
            if (! is_int($input['posted_within']) || ! in_array($input['posted_within'], self::POSTED_WITHIN, true)) {
                throw new BusinessRuleViolation('The posting window must be one of: :days days.', 'invalid-criteria', parameters: ['days' => implode(', ', self::POSTED_WITHIN)]);
            }

            $filters['posted_within'] = $input['posted_within'];
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

    /**
     * The words of the keyword in lower case, each once: the job search needs every one of them to match.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        $words = [];

        foreach (preg_split('/\s+/u', is_string($this->filters['q'] ?? null) ? trim($this->filters['q']) : '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $words[mb_strtolower($word)] = true;
        }

        return array_map('strval', array_keys($words));
    }

    /** @return list<mixed> */
    private static function list(mixed $value, string $name): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_VALUES) {
            throw new BusinessRuleViolation(':name must be a list of up to :max values.', 'invalid-criteria', parameters: ['name' => $name, 'max' => self::MAX_VALUES]);
        }

        return $value;
    }

    /** @return list<int> */
    private static function ids(mixed $value, string $name): array
    {
        $ids = [];

        foreach (self::list($value, $name) as $id) {
            if (! is_int($id) || $id < 1) {
                throw new BusinessRuleViolation(':name must hold positive whole numbers.', 'invalid-criteria', parameters: ['name' => $name]);
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }
}
