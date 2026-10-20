<?php

namespace Src\LegacyImport\Application;

use stdClass;

/**
 * Imports `candidate_education`. The level comes through the master map (required), the institution from `name`, and the single legacy `year` is read as the
 * graduation year: the entry is imported with start and end both that year (no invented range). The GPA (`grade`) is kept when it is a number (a comma
 * is read as the decimal point) below 1,000 (the column holds 999.99), whatever its value, else emptied with a note. The values are not checked against the new rules (`HistoryRules` apply to new data only);
 * a row without a level, an institution or a readable year is skipped (the columns cannot be empty). No limit per user.
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

    protected function skippedSummaryCode(): string
    {
        return 'education_skipped';
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
            $institution === null => 'institution-missing',
            $year === null => 'year-unreadable',
            default => null,
        };

        if ($reason !== null) {
            return $reason;
        }

        $gpa = $this->gpa($row->grade, $userId, $context);
        $field = LegacyText::limit(LegacyText::clean($row->field), 100);
        $description = LegacyText::plain($row->notes);

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

    private function gpa(mixed $grade, int $userId, ImportContext $context): ?string
    {
        $text = LegacyText::clean($grade === null ? null : (string) $grade);

        if ($text === null) {
            return null;
        }

        $number = str_replace(',', '.', $text);

        if (is_numeric($number) && abs((float) $number) < 1000) {
            return $number;
        }

        $context->report->note(self::ENTITY, 'gpa-invalid');
        $context->summary->jobSeeker($userId, 'gpa_emptied');

        return null;
    }
}
