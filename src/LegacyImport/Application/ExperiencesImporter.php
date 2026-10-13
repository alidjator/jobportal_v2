<?php

namespace Src\LegacyImport\Application;

use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports `candidate_experiences`. The job title is `job_title`, else `designation`; the company and the start are required (the columns cannot be empty): a row without one is skipped.
 * Every other value is kept as it is, with no check of the new rules (`HistoryRules` apply to new data only): a short title, a start in the future, an end before the start,
 * an end that cannot be read (left empty). Dates are read from the legacy free text; a job that is current has no end. The description is plain text; the employment type
 * is matched to a job type by name, else left empty. No limit per user.
 */
final class ExperiencesImporter extends CandidateListImporter
{
    public const string ENTITY = 'experiences';

    protected function table(): string
    {
        return 'candidate_experiences';
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
        return 'experience';
    }

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function __construct(
        LegacySource $legacy,
        JobSeekerWriter $writer,
        TransactionManager $transactions,
        private readonly NewMasterReader $masters,
    ) {
        parent::__construct($legacy, $writer, $transactions);
    }

    protected function lookups(ImportContext $context, array $rows): array
    {
        $byName = [];

        foreach ($this->masters->rows('reference_job_types') as $type) {
            foreach ($type['names'] as $name) {
                $byName[NameNormalizer::normalize($name)][$type['id']] = $type['id'];
            }
        }

        return ['jobTypes' => $byName];
    }

    protected function map(stdClass $row, int $userId, ImportContext $context, array $lookups): array|string
    {
        $company = LegacyText::limit(LegacyText::clean((string) $row->company), 255);
        $title = LegacyText::limit(LegacyText::clean($row->job_title) ?? LegacyText::clean($row->designation), 100);
        $start = LegacyDates::month($row->start);
        $current = (int) $row->currently_working === 1;
        $end = $current ? null : LegacyDates::month($row->end);

        $reason = match (true) {
            $company === null => 'company-invalid',
            $title === null => 'job-title-invalid',
            $start === null => 'start-date-unreadable',
            default => null,
        };

        if ($reason !== null) {
            return $reason;
        }

        $description = LegacyText::plain($row->responsibilities);

        $jobType = null;

        if (LegacyText::clean($row->employment_type) !== null) {
            $candidates = $lookups['jobTypes'][NameNormalizer::normalize((string) $row->employment_type)] ?? [];

            if (count($candidates) === 1) {
                $jobType = (int) reset($candidates);
            } else {
                $context->report->note(self::ENTITY, 'job-type-unmatched');
            }
        }

        return [
            'job_title' => $title,
            'company_name' => $company,
            'job_type_id' => $jobType,
            'location_text' => null,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),
            'is_current' => $current,
            'description' => $description,
            'created_at' => $this->time($row->created_at, $context),
            'updated_at' => $this->time($row->updated_at ?? $row->created_at, $context),
        ];
    }
}
