<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\JobSeeker\Domain\HistoryRules;
use Src\JobSeeker\Domain\WorkExperience;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Imports `candidate_experiences`. The job title is `job_title`, else `designation`; the company, start and end are required and must pass the new rules
 * (`HistoryRules`): a row that does not is skipped with the first field that failed. Dates are read from the legacy free text; a job that is current has no end.
 * The description is plain text cut to its limit; the employment type is matched to a job type by name, else left empty. At most 30 per user.
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

    protected function limit(): int
    {
        return HistoryRules::EXPERIENCES_MAX;
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
        $company = LegacyText::limit(LegacyText::clean((string) $row->company), 100);
        $title = LegacyText::limit(LegacyText::clean($row->job_title) ?? LegacyText::clean($row->designation), 100);
        $start = LegacyDates::month($row->start);
        $current = (int) $row->currently_working === 1;
        $end = $current ? null : LegacyDates::month($row->end);

        $reason = match (true) {
            $company === null || mb_strlen($company) < 2 => 'company-invalid',
            $title === null || mb_strlen($title) < 3 => 'job-title-invalid',
            $start === null => 'start-date-unreadable',
            ! $current && $end === null => 'end-date-unreadable',
            default => null,
        };

        if ($reason !== null) {
            return $reason;
        }

        $description = LegacyText::limit(LegacyText::plain($row->responsibilities), 2000);
        $thisMonth = new DateTimeImmutable($context->clock->now()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-01'), new DateTimeZone('UTC'));

        try {
            HistoryRules::experience(new WorkExperience($userId, $title, $company, null, null, $start, $end, $current, $description), $thisMonth);
        } catch (BusinessRuleViolation $e) {
            return 'invalid-'.array_key_first($e->extensions()['errors'] ?? ['experience' => []]);
        }

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
