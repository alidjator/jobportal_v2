<?php

namespace Src\Job\Application\Insight;

use DateTimeImmutable;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What the jobs on this platform pay for a role, for anyone (J10a). The figures come from the jobs published in the last 12 months that show a monthly salary
 * (hidden salaries, other periods, drafts and the jobs of companies the public cannot see never count). A group is big enough when it has at least
 * `analytics.min_group_size` jobs and at least 3 different companies, so one company never shows its own pay. A group that is too small widens step by step, from
 * the most specific to the least: with a city, city, province, the whole country; with a province, province, the whole country. At every place the asked
 * experience level is tried first, then none. When a category was given and even the role alone is too small, the last step is every job of that category.
 * The first group that is big enough is answered; when none is, the answer has no figures (and no count: a count below the minimum is never shown).
 */
final readonly class GetSalaryInsight
{
    public const int WINDOW_MONTHS = 12;

    public const int MIN_COMPANIES = 3;

    public const string MONTHLY = 'monthly';

    public function __construct(
        private SalaryInsightReader $reader,
        private ReferenceValues $references,
        private LocationReferences $locations,
        private PublicCompanies $publicCompanies,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `invalid-reference` for an id that does not exist or is inactive, or a city outside the given province */
    public function execute(SalaryInsightQuery $query, string $language): SalaryInsight
    {
        $provinceId = $this->checkedProvince($query);

        $now = $this->clock->now();
        $monthly = $this->references->idBySlug('salary-periods', self::MONTHLY);
        $minJobs = $this->settings->integer('analytics.min_group_size');
        $since = $now->modify('-'.self::WINDOW_MONTHS.' months');
        $found = $monthly === null ? null : $this->firstBigEnough($this->groups($query, $provinceId), $since, $monthly, $minJobs, $this->publicCompanies->hiddenIds());

        if ($found === null) {
            // Nothing is big enough: the answer describes what was asked, without figures.
            return $this->answer(new SalaryGroup($query->roleId, null, $provinceId, $query->cityId, $query->experienceLevelId), null, false, $query, $language, $now);
        }

        [$group, $figures, $widened] = $found;

        return $this->answer($group, $figures, $widened, $query, $language, $figures->computedAt);
    }

    /**
     * @param  list<SalaryGroup>  $groups  from the most specific to the least
     * @param  list<int>  $hidden
     * @return array{0: SalaryGroup, 1: SalaryFigures, 2: bool}|null the first group with enough jobs, its figures, and whether it is not the first one asked
     */
    private function firstBigEnough(array $groups, DateTimeImmutable $since, int $monthly, int $minJobs, array $hidden): ?array
    {
        foreach ($groups as $position => $group) {
            $figures = $this->reader->figures($group, $since, $monthly, $minJobs, self::MIN_COMPANIES, $hidden);

            if ($figures !== null) {
                return [$group, $figures, $position > 0];
            }
        }

        return null;
    }

    /** @return list<SalaryGroup> from the most specific to the least */
    private function groups(SalaryInsightQuery $query, ?int $provinceId): array
    {
        $places = match (true) {
            $query->cityId !== null => [[$provinceId, $query->cityId], [$provinceId, null], [null, null]],
            $provinceId !== null => [[$provinceId, null], [null, null]],
            default => [[null, null]],
        };
        $levels = $query->experienceLevelId === null ? [null] : [$query->experienceLevelId, null];
        $groups = [];

        foreach ($places as [$province, $city]) {
            foreach ($levels as $level) {
                $groups[] = new SalaryGroup($query->roleId, null, $province, $city, $level);
            }
        }

        if ($query->categoryId !== null) {
            $groups[] = new SalaryGroup(null, $query->categoryId, null, null, null);
        }

        return $groups;
    }

    private function answer(SalaryGroup $group, ?SalaryFigures $figures, bool $widened, SalaryInsightQuery $query, string $language, DateTimeImmutable $updatedAt): SalaryInsight
    {
        $names = $this->locations->names($group->provinceId, $group->cityId, null, null, null);
        $named = fn (string $master, ?int $id): ?array => $id === null ? null : ['id' => $id, 'name' => $this->references->name($master, $id, $language)];

        // Without figures the answer describes what was asked, not a group that was used.
        $level = $group->roleId === null ? 'category' : 'role';

        return new SalaryInsight(
            self::WINDOW_MONTHS,
            ['id' => $query->roleId, 'name' => $this->references->name('job-roles', $query->roleId, $language)],
            $figures,
            $level,
            $named('job-categories', $group->categoryId),
            $group->provinceId === null ? null : ['id' => $group->provinceId, 'name' => $names->province],
            $group->cityId === null ? null : ['id' => $group->cityId, 'name' => $names->city],
            $named('experience-levels', $group->experienceLevelId),
            $widened,
            $updatedAt,
        );
    }

    /** Checks every id and returns the province (the one given, or the city's own). */
    private function checkedProvince(SalaryInsightQuery $query): ?int
    {
        $errors = [];

        if (! $this->references->isActive('job-roles', $query->roleId)) {
            $errors['role_id'] = ['This value does not exist or is not active.'];
        }

        if ($query->experienceLevelId !== null && ! $this->references->isActive('experience-levels', $query->experienceLevelId)) {
            $errors['experience_level_id'] = ['This value does not exist or is not active.'];
        }

        if ($query->categoryId !== null && ! $this->references->isActive('job-categories', $query->categoryId)) {
            $errors['category_id'] = ['This value does not exist or is not active.'];
        }

        $provinceId = $query->provinceId;

        if ($query->cityId !== null) {
            $cityProvince = $this->locations->provinceOfCity($query->cityId);

            if ($cityProvince === null || ($provinceId !== null && $provinceId !== $cityProvince)) {
                $errors['city_id'] = ['This city does not exist or does not belong to the province.'];
            } else {
                $provinceId = $cityProvince;
            }
        }

        if ($provinceId !== null && ! isset($errors['city_id']) && ! $this->locations->isValidChain($provinceId, $query->cityId, null, null, null)) {
            $errors[$query->cityId === null ? 'province_id' : 'city_id'] = ['This value does not exist.'];
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('Some of the values do not exist or are not active.', 'invalid-reference', extensions: ['errors' => $errors]);
        }

        return $provinceId;
    }
}
