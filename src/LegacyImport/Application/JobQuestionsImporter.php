<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `company_question_job` as the attachment of library questions to jobs, in the legacy order, with the legacy `required` flag of the question
 * as the per-job required flag (no ideal answer, never a must-have). A job with more questions than the limit of the new rules is imported whole (the limit
 * applies when editing) and counted in the report. A link with no job or no question, to a job or question that was not imported, or between a job and a
 * question of two different companies is skipped.
 */
final class JobQuestionsImporter implements Importer
{
    public const string ENTITY = 'job-questions';

    /** The id-map entity under which a legacy link id is remembered. */
    public const string LINK = 'job-question';

    private const int BATCH = 200;

    /** @var array<int, int> the number of questions of each new job, counted while the run goes on */
    private array $counts = [];

    /** @var array<string, true> the pairs (new job id, new question id) this run has seen */
    private array $seen = [];

    /** @var array<int, true> the new jobs already counted as over the limit */
    private array $over = [];

    public function __construct(
        private readonly LegacySource $legacy,
        private readonly ScreeningWriter $screening,
        private readonly Settings $settings,
        private readonly TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [JobsImporter::ENTITY, QuestionsImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $this->counts = [];
        $this->seen = [];
        $this->over = [];

        $this->legacy->chunk('company_question_job', self::BATCH, function (array $rows) use ($context): void {
            $jobIds = $this->ids($rows, 'job_id');
            $questionIds = $this->ids($rows, 'company_question_id');
            $jobCompanies = [];
            $questions = [];

            foreach ($this->legacy->whereIn('jobs', 'id', $jobIds, ['id', 'company_id']) as $job) {
                $jobCompanies[(int) $job->id] = $job->company_id === null ? null : (int) $job->company_id;
            }

            foreach ($this->legacy->whereIn('company_questions', 'id', $questionIds, ['id', 'company_id', 'required']) as $question) {
                $questions[(int) $question->id] = ['company' => (int) $question->company_id, 'required' => (int) $question->required === 1];
            }

            $state = [
                'already' => $context->idMap->findMany(self::LINK, array_map(static fn (stdClass $row): int => (int) $row->id, $rows)),
                'jobs' => $context->idMap->findMany(JobsImporter::JOB, $jobIds),
                'questions' => $context->idMap->findMany(QuestionsImporter::QUESTION, $questionIds),
                'jobCompanies' => $jobCompanies,
                'legacyQuestions' => $questions,
                'companies' => $context->idMap->findMany(CompaniesImporter::COMPANY, array_values(array_unique(array_filter($jobCompanies)))),
            ];

            $this->transactions->run(function () use ($rows, $context, $state): void {
                foreach ($rows as $row) {
                    $this->importRow($row, $context, $state);
                }
            });
        });
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<int>
     */
    private function ids(array $rows, string $column): array
    {
        return array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->{$column} === null ? null : (int) $row->{$column}, $rows))));
    }

    /** @param  array<string, mixed>  $state */
    private function importRow(stdClass $row, ImportContext $context, array $state): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($state['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $legacyJob = $row->job_id === null ? null : (int) $row->job_id;
        $legacyQuestion = $row->company_question_id === null ? null : (int) $row->company_question_id;
        $jobId = $legacyJob === null ? null : ($state['jobs'][$legacyJob] ?? null);
        $questionId = $legacyQuestion === null ? null : ($state['questions'][$legacyQuestion] ?? null);

        $reason = match (true) {
            $legacyJob === null || $legacyQuestion === null => 'link-incomplete',
            $jobId === null => 'job-not-imported',
            $questionId === null => 'question-not-imported',
            ($state['jobCompanies'][$legacyJob] ?? null) !== ($state['legacyQuestions'][$legacyQuestion]['company'] ?? null) => 'company-mismatch',
            isset($this->seen[$jobId.':'.$questionId]) || $this->screening->isAttached($jobId, $questionId) => 'duplicate-link',
            default => null,
        };

        $companyId = $legacyJob === null ? null : ($state['companies'][$state['jobCompanies'][$legacyJob] ?? 0] ?? null);

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            if ($companyId !== null) {
                $context->summary->company($companyId, 'screening_questions_skipped', $reason);
            }

            return;
        }

        $this->counts[$jobId] ??= $this->screening->attachedCount($jobId);
        $this->counts[$jobId]++;
        $this->seen[$jobId.':'.$questionId] = true;

        if ($this->counts[$jobId] > $this->settings->integer(RecruitmentSettings::MAX_SCREENING_QUESTIONS) && ! isset($this->over[$jobId])) {
            $this->over[$jobId] = true;
            $report->note(self::ENTITY, 'job-over-question-limit');

            if ($companyId !== null) {
                $context->summary->company($companyId, 'screening_questions_over_limit');
            }
        }

        if ($context->write) {
            $createdAt = $this->time($row->created_at, $context);
            $newId = $this->screening->attach($jobId, $questionId, $this->counts[$jobId], (bool) $state['legacyQuestions'][$legacyQuestion]['required'], $createdAt);
            $context->idMap->remember(self::LINK, $legacyId, $newId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
