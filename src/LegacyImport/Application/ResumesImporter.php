<?php

namespace Src\LegacyImport\Application;

use Src\JobSeeker\Application\JobSeekerSettings;
use Src\JobSeeker\Domain\ResumePdf;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;
use Throwable;

/**
 * Imports `candidate_resumes`: the file is read from the legacy project folder and copied to the private disk under a random name only when it passes the new
 * rules: a PDF checked by content, the simple scan for encryption, scripts, launch actions and embedded files, the admin size limit, and at most five per user
 * (the newest five that pass; older ones are skipped). Every other one is skipped with its reason so the owner can upload it again. A dry run reads and checks
 * the files but stores nothing.
 */
final readonly class ResumesImporter implements Importer
{
    public const string ENTITY = 'resumes';

    /** The id-map entity under which a legacy resume id is remembered. */
    public const string RESUME = 'resume';

    private const string DIRECTORY = 'jobseeker/resumes';

    public function __construct(
        private LegacySource $legacy,
        private LegacyFiles $files,
        private FileStorage $storage,
        private JobSeekerWriter $writer,
        private Settings $settings,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [AccountsImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $rows = $this->legacy->all('candidate_resumes', ['id', 'candidate_id', 'name', 'file', 'created_at']);
        $candidateIds = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->candidate_id, $rows)));
        $owners = [];

        foreach ($this->legacy->whereIn('candidates', 'id', $candidateIds, ['id', 'user_id']) as $candidate) {
            $owners[(int) $candidate->id] = (int) $candidate->user_id;
        }

        $users = $context->idMap->findMany(AccountsImporter::USER, array_values($owners));
        $done = $context->idMap->findMany(self::RESUME, array_map(static fn (stdClass $row): int => (int) $row->id, $rows));

        // The newest first, so that the five that stay are the newest five that pass.
        usort($rows, static fn (stdClass $a, stdClass $b): int => [(int) $a->candidate_id, (int) $b->id] <=> [(int) $b->candidate_id, (int) $a->id]);

        $counts = [];
        $maxBytes = $this->settings->integer(JobSeekerSettings::RESUME_MAX_SIZE_KB) * 1024;

        foreach ($rows as $row) {
            $this->importRow($row, $users[$owners[(int) $row->candidate_id] ?? 0] ?? null, isset($done[(int) $row->id]), $maxBytes, $counts, $context);
        }
    }

    /** @param  array<int, int>  $counts */
    private function importRow(stdClass $row, ?int $userId, bool $alreadyImported, int $maxBytes, array &$counts, ImportContext $context): void
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

        $counts[$userId] ??= $this->writer->count('resume', $userId);

        if ($counts[$userId] >= ResumePdf::RESUMES_MAX) {
            $report->skipped(self::ENTITY, $legacyId, 'over-limit');

            return;
        }

        $contents = $this->files->isConfigured() ? $this->files->read((string) $row->file) : null;

        $reason = match (true) {
            ! $this->files->isConfigured() => 'files-root-not-configured',
            $contents === null => 'file-missing',
            strlen($contents) > $maxBytes => 'too-large',
            ! str_starts_with($contents, '%PDF-') => 'not-a-pdf',
            default => $this->unsafe($contents) ? 'unsafe-pdf' : null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            return;
        }

        $counts[$userId]++;

        if ($context->write) {
            $this->store($row, $userId, (string) $contents, $context);
        }

        $report->imported(self::ENTITY);
    }

    private function store(stdClass $row, int $userId, string $contents, ImportContext $context): void
    {
        $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.pdf';
        $this->storage->put($path, $contents);

        try {
            $this->transactions->run(function () use ($row, $userId, $contents, $path, $context): void {
                $name = ResumePdf::cleanName(LegacyText::clean($row->name) ?? basename((string) $row->file));
                $id = $this->writer->insert('resume', [
                    'user_id' => $userId,
                    'name' => $name,
                    'path' => $path,
                    'size_bytes' => strlen($contents),
                    'created_at' => $row->created_at ?? $context->clock->now()->format('Y-m-d H:i:s'),
                ]);

                $context->idMap->remember(self::RESUME, (int) $row->id, $id, $context->clock->now());
            });
        } catch (Throwable $e) {
            $this->storage->delete($path);

            throw $e;
        }
    }

    private function unsafe(string $contents): bool
    {
        try {
            ResumePdf::check($contents);
        } catch (BusinessRuleViolation) {
            return true;
        }

        return false;
    }
}
