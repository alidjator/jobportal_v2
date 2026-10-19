<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\ApplicationFacets;
use Src\Recruitment\Domain\ExperienceMonths;
use Src\Shared\Application\References\ReferenceValues;

/**
 * Turns the profile snapshot of an application into its facets (`docs/design/phase-8-applicant-search.md` section 1). Experience is counted up to the day of
 * applying, in Jakarta time. An expected salary is kept only when it is given per month. A snapshot written before the location ids were added gives no
 * province and no city.
 */
final readonly class ApplicationFacetBuilder
{
    private const string MONTHLY = 'monthly';

    public function __construct(private ReferenceValues $references) {}

    /** @param  array<string, mixed>  $snapshot */
    public function build(array $snapshot, DateTimeImmutable $appliedAt, bool $fromInvitation, bool $returning): ApplicationFacets
    {
        $today = $appliedAt->setTimezone(new DateTimeZone(StageClock::ZONE));
        $educations = array_values(array_filter($snapshot['educations'] ?? [], 'is_array'));
        $levelIds = array_values(array_filter(array_map(static fn (array $entry): ?int => $entry['level']['id'] ?? null, $educations), 'is_int'));
        $ranks = $levelIds === [] ? [] : $this->references->educationRanks($levelIds);
        $experiences = array_values(array_filter($snapshot['experiences'] ?? [], 'is_array'));
        $skills = array_values(array_filter($snapshot['skills'] ?? [], 'is_array'));

        return new ApplicationFacets(
            $snapshot['location']['province_id'] ?? null,
            $snapshot['location']['city_id'] ?? null,
            $ranks === [] ? null : max($ranks),
            ExperienceMonths::of($experiences, $today),
            $this->monthlySalary($snapshot['expected_salary'] ?? null),
            array_values(array_unique(array_map(static fn (array $skill): int => (int) $skill['id'], $skills))),
            $this->searchText($snapshot, $experiences, $educations, $skills),
            $fromInvitation,
            $returning,
        );
    }

    /** @param  array{amount?: int, period?: array{id?: int}|null}|null  $salary */
    private function monthlySalary(?array $salary): ?int
    {
        $periodId = $salary['period']['id'] ?? null;

        if ($salary === null || ! isset($salary['amount']) || $periodId === null || $periodId !== $this->references->idBySlug('salary-periods', self::MONTHLY)) {
            return null;
        }

        return (int) $salary['amount'];
    }

    /**
     * The words the keyword search looks in: headline, job titles and employers, skill names (both languages), schools and fields of study, and the city.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $experiences
     * @param  list<array<string, mixed>>  $educations
     * @param  list<array<string, mixed>>  $skills
     */
    private function searchText(array $snapshot, array $experiences, array $educations, array $skills): string
    {
        $parts = [$snapshot['headline'] ?? null, $snapshot['location']['city'] ?? null];

        foreach ($experiences as $entry) {
            array_push($parts, $entry['job_title'] ?? null, $entry['company_name'] ?? null);
        }

        foreach ($educations as $entry) {
            array_push($parts, $entry['institution'] ?? null, $entry['field_of_study'] ?? null);
        }

        foreach ($skills as $skill) {
            array_push($parts, $skill['name']['id'] ?? null, $skill['name']['en'] ?? null);
        }

        return implode(' ', array_unique(array_filter(array_map(static fn (mixed $part): string => is_string($part) ? trim($part) : '', $parts), static fn (string $part): bool => $part !== '')));
    }
}
