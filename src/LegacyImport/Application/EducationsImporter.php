<?php

namespace Src\LegacyImport\Application;

use DateTimeZone;
use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\HistoryRules;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Imports `candidate_education`. The level comes through the master map (required), the institution from `name`, and the single legacy `year` is read as the
 * graduation year: the entry is imported with start and end both that year (no invented range). The GPA (`grade`) is kept when it is a number from 0 to 4
 * with at most two decimals, else emptied with a note. A row that fails the new rules (`HistoryRules`) is skipped with the first field that failed.
 */
final class EducationsImporter extends CandidateListImporter
{
    public const string ENTITY = 'educations';

    protected function table(): string
    {
        return 'candidate_education';
    }

    protected function candidateColumn(): string
    {
        return 'candidate_id';
    }

    protected function itemEntity(): string
    {
        return self::ENTITY;
    }

    protected function kind(): string
    {
        return 'education';
    }

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [ProfilesImporter::ENTITY, MastersImporter::ENTITY];
    }

    protected function lookups(ImportContext $context, array $rows): array
    {
        $levels = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->level === null ? null : (int) $row->level, $rows))));

        return ['levels' => $context->idMap->findMany(MasterCatalog::entityFor('education_levels'), $levels)];
    }

    protected function map(stdClass $row, int $userId, ImportContext $context, array $lookups): array|string
    {
        $level = $row->level === null ? null : ($lookups['levels'][(int) $row->level] ?? null);
        $institution = LegacyText::limit(LegacyText::clean($row->name), 150);
        $year = LegacyDates::year($row->year);

        $reason = match (true) {
            $level === null => 'education-level-unmatched',
            $institution === null || mb_strlen($institution) < 2 => 'institution-missing',
            $year === null => 'year-unreadable',
            default => null,
        };

        if ($reason !== null) {
            return $reason;
        }

        $gpa = $this->gpa($row->grade, $context);
        $field = LegacyText::limit(LegacyText::clean($row->field), 100);
        $description = LegacyText::limit(LegacyText::plain($row->notes), 1000);
        $thisYear = (int) $context->clock->now()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y');

        try {
            HistoryRules::education(new Education($userId, (int) $level, $institution, $field, $year, $year, false, $gpa, $description), $thisYear);
        } catch (BusinessRuleViolation $e) {
            return 'invalid-'.array_key_first($e->extensions()['errors'] ?? ['education' => []]);
        }

        return [
            'education_level_id' => $level,
            'institution' => $institution,
            'field_of_study' => $field,
            'start_year' => $year,
            'end_year' => $year,
            'is_current' => false,
            'gpa' => $gpa,
            'description' => $description,
            'created_at' => $this->time($row->created_at, $context),
            'updated_at' => $this->time($row->updated_at ?? $row->created_at, $context),
        ];
    }

    private function gpa(mixed $grade, ImportContext $context): ?string
    {
        $text = LegacyText::clean($grade === null ? null : (string) $grade);

        if ($text === null) {
            return null;
        }

        $number = str_replace(',', '.', $text);

        if (preg_match('/^[0-4](\.[0-9]{1,2})?$/', $number) === 1 && (float) $number <= 4.0) {
            return $number;
        }

        $context->report->note(self::ENTITY, 'gpa-invalid');

        return null;
    }
}
