<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the saved jobs (`bookmark_candidate_job`): `saved_at` is the legacy `created_at` (the import time when it is empty). A bookmark whose job was not imported (a row without a job, an
 * apply-outside copy that was not kept, a pending-edit copy) or whose job seeker was not imported is skipped and reported; a job that exists but is not live is kept and the owner is told how many
 * are unavailable; a job saved twice collapses on the unique key. The limit of 200 saved jobs is not applied: the legacy data is migrated as it is. `docs/design/phase-8-activation.md` section 9.
 */
final readonly class SavedJobsImporter implements Importer
{
    public const string ENTITY = 'saved-jobs';

    /** The id-map entity under which a legacy `bookmark_candidate_job` id is remembered. */
    public const string SAVED_JOB = 'saved_job';

    private const int BATCH = 200;

    public function __construct(
        private LegacySource $legacy,
        private SavedJobWriter $writer,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [AccountsImporter::ENTITY, JobsImporter::ENTITY, ApplicationsImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        /** @var array<string, true> $seen the (user, job) pairs saved so far in this run, so a dry run collapses duplicates too */
        $seen = [];

        $this->legacy->chunk('bookmark_candidate_job', self::BATCH, function (array $rows) use ($context, &$seen): void {
            $candidateIds = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->candidate_id, $rows)));
            $owners = [];

            foreach ($this->legacy->whereIn('candidates', 'id', $candidateIds, ['id', 'user_id']) as $candidate) {
                $owners[(int) $candidate->id] = (int) $candidate->user_id;
            }

            $users = $context->idMap->findMany(AccountsImporter::USER, array_values($owners));
            $jobs = $context->idMap->findMany(JobsImporter::JOB, array_values(array_filter(array_map(static fn (stdClass $row): ?int => $row->job_id === null ? null : (int) $row->job_id, $rows))));
            $already = $context->idMap->findMany(self::SAVED_JOB, array_map(static fn (stdClass $row): int => (int) $row->id, $rows));
            $notLive = array_flip($this->writer->notLive(array_values($jobs)));

            $this->transactions->run(function () use ($rows, $context, $owners, $users, $jobs, $already, $notLive, &$seen): void {
                foreach ($rows as $row) {
                    $userId = isset($owners[(int) $row->candidate_id]) ? ($users[$owners[(int) $row->candidate_id]] ?? null) : null;
                    $jobId = $row->job_id === null ? null : ($jobs[(int) $row->job_id] ?? null);

                    $this->importRow($row, $userId, $jobId, isset($already[(int) $row->id]), $jobId !== null && isset($notLive[$jobId]), $context, $seen);
                }
            });
        });
    }

    /** @param  array<string, true>  $seen */
    private function importRow(stdClass $row, ?int $userId, ?int $jobId, bool $alreadyImported, bool $unavailable, ImportContext $context, array &$seen): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if ($alreadyImported) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        if ($userId === null) {
            $report->skipped(self::ENTITY, $legacyId, 'user-not-imported');

            return;
        }

        if ($jobId === null) {
            $report->skipped(self::ENTITY, $legacyId, $row->job_id === null ? 'job-missing' : 'job-not-imported');
            $context->summary->jobSeeker($userId, 'saved_jobs_skipped', $row->job_id === null ? 'job_missing' : 'job_not_imported');

            return;
        }

        if (isset($seen["{$userId}:{$jobId}"])) {
            $report->skipped(self::ENTITY, $legacyId, 'duplicate');

            return;
        }

        $seen["{$userId}:{$jobId}"] = true;
        $savedAt = $this->time($row->created_at, $context);

        if ($context->write) {
            $newId = $this->writer->insert($userId, $jobId, $savedAt);

            if ($newId === null) {
                $report->skipped(self::ENTITY, $legacyId, 'duplicate');

                return;
            }

            $context->idMap->remember(self::SAVED_JOB, $legacyId, $newId, $context->clock->now());
        }

        $context->summary->jobSeeker($userId, 'saved_jobs_imported');

        if ($unavailable) {
            $context->summary->jobSeeker($userId, 'saved_jobs_imported', 'unavailable', inTotal: false);
            $report->note(self::ENTITY, 'job-not-live');
        }

        $report->imported(self::ENTITY);
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
