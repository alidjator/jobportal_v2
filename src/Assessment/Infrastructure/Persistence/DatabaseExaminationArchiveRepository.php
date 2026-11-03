<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Domain\ArchivedExamination;
use Src\Assessment\Domain\ExaminationArchiveRepository;
use Src\Assessment\Domain\ExaminationSnapshot;
use Src\Assessment\Domain\TestKind;
use stdClass;

/** The archive of erased psychological examinations (`docs/design/phase-8-provider-institutions.md` section 7). The payload is encrypted with the application key. */
final readonly class DatabaseExaminationArchiveRepository implements ExaminationArchiveRepository
{
    private const string TABLE = 'assessment_archived_examinations';

    public function __construct(
        private ConnectionInterface $connection,
        private Encrypter $encrypter,
    ) {}

    public function snapshotsOf(int $userId): array
    {
        $attempts = $this->psychologicalAttempts($userId)
            ->whereNotIn('attempts.id', fn (Builder $archived) => $archived->from(self::TABLE)->select('attempt_id'))
            ->orderBy('attempts.id')
            ->get(['attempts.id', 'attempts.norm_age', 'attempts.norm_education_rank', 'tests.provider_id']);

        return array_map($this->snapshotOf(...), $attempts->all());
    }

    public function add(ArchivedExamination $examination): void
    {
        $this->connection->table(self::TABLE)->insert([
            'attempt_id' => $examination->attemptId,
            'provider_id' => $examination->providerId,
            'report_number' => $examination->reportNumber,
            'archived_at' => $examination->archivedAt,
            'retain_until' => $examination->retainUntil->format('Y-m-d'),
            'payload' => $this->encrypter->encryptString(json_encode($examination->payload, JSON_THROW_ON_ERROR)),
            'files' => json_encode($examination->files, JSON_THROW_ON_ERROR),
        ]);
    }

    public function findByReportNumber(string $reportNumber): array
    {
        return array_map($this->hydrate(...), $this->connection->table(self::TABLE)->where('report_number', $reportNumber)->orderBy('id')->get()->all());
    }

    public function endedBefore(DateTimeImmutable $day, int $limit): array
    {
        return array_map($this->hydrate(...), $this->connection->table(self::TABLE)->where('retain_until', '<', $day->format('Y-m-d'))->orderBy('id')->limit($limit)->get()->all());
    }

    public function remove(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function psychologicalAttemptCount(int $userId): int
    {
        return $this->psychologicalAttempts($userId)->count();
    }

    private function psychologicalAttempts(int $userId): Builder
    {
        return $this->connection->table('assessment_attempts as attempts')
            ->join('assessment_invitations as invitations', 'invitations.id', '=', 'attempts.invitation_id')
            ->join('assessment_tests as tests', 'tests.id', '=', 'invitations.test_id')
            ->where('invitations.applicant_user_id', $userId)
            ->where('tests.kind', TestKind::Psychological->value);
    }

    private function snapshotOf(stdClass $attempt): ExaminationSnapshot
    {
        $attemptId = (int) $attempt->id;
        $sources = [];
        $source = static function (string $kind, string $path) use (&$sources): int {
            $sources[] = ['kind' => $kind, 'path' => $path, 'name' => basename($path)];

            return count($sources) - 1;
        };

        $answers = $this->connection->table('assessment_answers')->where('attempt_id', $attemptId)->orderBy('id')
            ->get(['question_id', 'option_positions', 'number_value', 'text_value', 'score', 'answered_at'])
            ->map(static fn (stdClass $row): array => (array) $row)->all();

        $drawings = [];

        foreach ($this->connection->table('assessment_drawings')->where('attempt_id', $attemptId)->orderBy('id')->get() as $drawing) {
            $drawings[] = [
                'question_id' => (int) $drawing->question_id, 'source' => $drawing->source, 'mime_type' => $drawing->mime_type, 'size_bytes' => (int) $drawing->size_bytes,
                'score' => $drawing->score, 'note' => $drawing->note, 'file' => $source('drawing', (string) $drawing->path),
            ];
        }

        $report = $this->connection->table('assessment_reports')->where('attempt_id', $attemptId)->first();
        $reportPayload = null;
        $revisions = [];

        if ($report !== null) {
            $reportPayload = [
                'status' => $report->status, 'report_number' => $report->report_number, 'body' => $report->body, 'explanation' => $report->explanation, 'review_notes' => $report->review_notes,
                'signed_at' => $report->signed_at, 'signer_name' => $report->signer_name, 'revision' => (int) $report->revision, 'pdf_sha256' => $report->pdf_sha256,
                'pdf_file' => $report->pdf_path === null ? null : $source('report_pdf', (string) $report->pdf_path),
            ];

            foreach ($this->connection->table('assessment_report_revisions')->where('report_id', $report->id)->orderBy('revision')->get() as $revision) {
                $revisions[] = [
                    'revision' => (int) $revision->revision, 'report_number' => $revision->report_number, 'body' => $revision->body, 'explanation' => $revision->explanation,
                    'signed_at' => $revision->signed_at, 'signer_name' => $revision->signer_name, 'revision_reason' => $revision->revision_reason, 'pdf_sha256' => $revision->pdf_sha256,
                    'pdf_file' => $revision->pdf_path === null ? null : $source('revision_pdf', (string) $revision->pdf_path),
                ];
            }
        }

        $scales = $this->connection->table('assessment_results')->where('attempt_id', $attemptId)->value('scale_scores');

        return new ExaminationSnapshot(
            $attemptId,
            (int) ($report->provider_id ?? $attempt->provider_id),
            $report?->report_number,
            [
                'norm_age' => $attempt->norm_age === null ? null : (int) $attempt->norm_age,
                'norm_education_rank' => $attempt->norm_education_rank === null ? null : (int) $attempt->norm_education_rank,
                'scale_scores' => $scales === null ? null : json_decode((string) $scales, true, 512, JSON_THROW_ON_ERROR),
                'answers' => $answers,
                'drawings' => $drawings,
                'report' => $reportPayload,
                'revisions' => $revisions,
            ],
            $sources,
        );
    }

    private function hydrate(stdClass $row): ArchivedExamination
    {
        $utc = new DateTimeZone('UTC');

        return new ArchivedExamination(
            (int) $row->id,
            (int) $row->attempt_id,
            (int) $row->provider_id,
            $row->report_number,
            new DateTimeImmutable((string) $row->archived_at, $utc),
            new DateTimeImmutable((string) $row->retain_until, $utc),
            (array) json_decode($this->encrypter->decryptString((string) $row->payload), true, 512, JSON_THROW_ON_ERROR),
            (array) json_decode((string) $row->files, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
