<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The content rules of a work experience and an education entry, checked on the whole entry (so a partial change is checked together with what stays). A value the entry already holds (migrated as it was) passes unchanged, so only a changed text or GPA is held to the limits. */
final class HistoryRules
{
    public const int EXPERIENCES_MAX = 30;

    public const int EDUCATIONS_MAX = 15;

    private const int FIRST_YEAR = 1950;

    private const int EXPECTED_GRADUATION_YEARS = 10;

    /**
     * @param  DateTimeImmutable  $thisMonth  the first day of the current month in Jakarta, midnight UTC
     *
     * @throws BusinessRuleViolation `invalid-experience` with `errors` per field
     */
    public static function experience(WorkExperience $experience, DateTimeImmutable $thisMonth, ?WorkExperience $current = null): void
    {
        $errors = [];

        self::length($errors, 'job_title', $experience->jobTitle, 3, 100, $current?->jobTitle);
        self::length($errors, 'company_name', $experience->companyName, 2, 100, $current?->companyName);
        self::length($errors, 'location_text', $experience->locationText, 1, 100, $current?->locationText);
        self::length($errors, 'description', $experience->description, 1, 2000, $current?->description);

        if ($experience->startDate > $thisMonth) {
            $errors['start_date'][] = 'The start cannot be in the future.';
        }

        if ($experience->isCurrent && $experience->endDate !== null) {
            $errors['end_date'][] = 'A current job has no end date.';
        } elseif (! $experience->isCurrent && $experience->endDate === null) {
            $errors['end_date'][] = 'A job that is not current needs an end date.';
        } elseif ($experience->endDate !== null && $experience->endDate < $experience->startDate) {
            $errors['end_date'][] = 'The end cannot be before the start.';
        } elseif ($experience->endDate !== null && $experience->endDate > $thisMonth) {
            $errors['end_date'][] = 'The end cannot be in the future.';
        }

        self::fail($errors, 'invalid-experience', 'The work experience is not valid.');
    }

    /**
     * @throws BusinessRuleViolation `invalid-education` with `errors` per field
     */
    public static function education(Education $education, int $thisYear, ?Education $current = null): void
    {
        $errors = [];

        self::length($errors, 'institution', $education->institution, 2, 150, $current?->institution);
        self::length($errors, 'field_of_study', $education->fieldOfStudy, 1, 100, $current?->fieldOfStudy);
        self::length($errors, 'description', $education->description, 1, 1000, $current?->description);

        if ($education->startYear < self::FIRST_YEAR || $education->startYear > $thisYear) {
            $errors['start_year'][] = 'The start year must be from '.self::FIRST_YEAR." to {$thisYear}.";
        }

        if ($education->isCurrent) {
            if ($education->endYear !== null && ($education->endYear < $education->startYear || $education->endYear > $thisYear + self::EXPECTED_GRADUATION_YEARS)) {
                $errors['end_year'][] = 'The expected end year must be from the start year up to '.($thisYear + self::EXPECTED_GRADUATION_YEARS).'.';
            }
        } elseif ($education->endYear === null) {
            $errors['end_year'][] = 'A finished education needs an end year.';
        } elseif ($education->endYear < $education->startYear || $education->endYear > $thisYear) {
            $errors['end_year'][] = "The end year must be from the start year up to {$thisYear}.";
        }

        if ($education->gpa !== null && ! self::sameNumber($education->gpa, $current?->gpa) && (preg_match('/^[0-4](\.[0-9]{1,2})?$/', $education->gpa) !== 1 || (float) $education->gpa > 4.0)) {
            $errors['gpa'][] = 'The GPA must be between 0.00 and 4.00 with at most two decimals.';
        }

        self::fail($errors, 'invalid-education', 'The education entry is not valid.');
    }

    private static function sameNumber(string $value, ?string $stored): bool
    {
        return $stored !== null && is_numeric($value) && is_numeric($stored) && (float) $value === (float) $stored;
    }

    /** @param  array<string, list<string>>  $errors */
    private static function length(array &$errors, string $field, ?string $value, int $min, int $max, ?string $unchanged = null): void
    {
        if ($value === null || ($unchanged !== null && trim($value) === trim($unchanged))) {
            return;
        }

        $length = mb_strlen(trim($value));

        if ($length < $min || $length > $max) {
            $errors[$field][] = "It must have between {$min} and {$max} characters.";
        }
    }

    /** @param  array<string, list<string>>  $errors */
    private static function fail(array $errors, string $code, string $message): void
    {
        if ($errors !== []) {
            throw new BusinessRuleViolation($message, $code, extensions: ['errors' => $errors]);
        }
    }
}
