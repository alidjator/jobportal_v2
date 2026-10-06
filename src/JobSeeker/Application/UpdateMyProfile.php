<?php

namespace Src\JobSeeker\Application;

use DateTimeImmutable;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A job seeker changes some fields of their profile; the row is created the first time. `$changes` holds only what changes, keyed by property name, and a
 * null clears an optional field. A changed master value must be active (a value an admin deactivated later does not block unrelated edits); the location
 * is always checked as one chain together with the part that does not change; an expected salary needs its period.
 */
final readonly class UpdateMyProfile
{
    private const array LOCATION_FIELDS = ['provinceId', 'cityId', 'subdistrictId', 'villageId', 'postalCodeId'];

    /** property => [master URL name, error field] */
    private const array MASTERS = [
        'religionId' => ['religions', 'religion_id'],
        'nationalityId' => ['nationalities', 'nationality_id'],
        'expectedSalaryPeriodId' => ['salary-periods', 'expected_salary_period_id'],
        'experienceLevelId' => ['experience-levels', 'experience_level_id'],
    ];

    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileRepository $profiles,
        private ReferenceValues $references,
        private LocationReferences $locations,
        private Clock $clock,
        private JakartaToday $today,
        private Settings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-profile` with `errors` per field, or `invalid-location`
     */
    public function execute(Actor $actor, array $changes): void
    {
        $userId = $this->guard->userId($actor);
        $profile = ($this->profiles->find($userId) ?? JobSeekerProfile::empty($userId))->with($changes);

        $this->assertMasters($changes);
        $this->assertDates($changes);

        if ($profile->expectedSalary !== null && $profile->expectedSalaryPeriodId === null) {
            throw $this->invalid('expected_salary_period_id', 'An expected salary needs its period.');
        }

        if (array_intersect(self::LOCATION_FIELDS, array_keys($changes)) !== []
            && ! $this->locations->isValidChain($profile->provinceId, $profile->cityId, $profile->subdistrictId, $profile->villageId, $profile->postalCodeId)) {
            throw new BusinessRuleViolation(
                'The location is not a matching chain: each level must belong to the one above it, and a level needs the levels above it.',
                'invalid-location',
            );
        }

        $this->profiles->save($profile, $this->clock->now());
    }

    /**
     * The birth date makes the job seeker at least the minimum age and at most the maximum age (admin settings), and the date from which they are
     * available is not past and at most the allowed number of days ahead (an admin setting). All are measured from today in Jakarta; only a date that is
     * being changed is checked.
     *
     * @param  array<string, mixed>  $changes
     */
    private function assertDates(array $changes): void
    {
        $today = $this->today->date();
        $birthDate = $changes['birthDate'] ?? null;
        $availableFrom = $changes['availableFrom'] ?? null;

        if ($birthDate instanceof DateTimeImmutable) {
            $minimum = $this->settings->integer(JobSeekerSettings::MIN_AGE);
            $maximum = $this->settings->integer(JobSeekerSettings::MAX_AGE);

            if ($birthDate > $today->modify("-{$minimum} years")) {
                throw $this->invalid('birth_date', "You must be at least {$minimum} years old.");
            }

            if ($birthDate <= $today->modify('-'.($maximum + 1).' years')) {
                throw $this->invalid('birth_date', "You must be at most {$maximum} years old.");
            }
        }

        if ($availableFrom instanceof DateTimeImmutable) {
            $days = $this->settings->integer(JobSeekerSettings::AVAILABLE_FROM_MAX_DAYS);

            if ($availableFrom < $today || $availableFrom > $today->modify("+{$days} days")) {
                throw $this->invalid('available_from', "The date must be from today up to {$days} days ahead.");
            }
        }
    }

    /** @param  array<string, mixed>  $changes */
    private function assertMasters(array $changes): void
    {
        foreach (self::MASTERS as $property => [$master, $field]) {
            $id = $changes[$property] ?? null;

            if ($id !== null && ! $this->references->isActive($master, (int) $id)) {
                throw $this->invalid($field, 'This value does not exist or is not active.');
            }
        }
    }

    private function invalid(string $field, string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-profile', extensions: ['errors' => [$field => [$message]]]);
    }
}
