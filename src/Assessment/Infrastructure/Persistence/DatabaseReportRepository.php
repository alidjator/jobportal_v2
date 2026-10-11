<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportStatus;
use Src\Assessment\Domain\ReportSummary;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseReportRepository implements ReportRepository
{
    private const string TABLE = 'assessment_reports';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id, bool $forUpdate = false): ?Report
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toReport($row);
    }

    public function findByAttempt(int $attemptId, bool $forUpdate = false): ?Report
    {
        $query = $this->connection->table(self::TABLE)->where('attempt_id', $attemptId);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toReport($row);
    }

    public function add(Report $report): Report
    {
        $now = $this->clock->now();
        $id = $this->connection->table(self::TABLE)->insertGetId($this->values($report) + ['attempt_id' => $report->attemptId, 'provider_id' => $report->providerId, 'created_at' => $now]);

        return new Report(
            (int) $id, $report->attemptId, $report->providerId, $report->status, $report->body, $report->explanation, $report->reviewNotes, $report->editedAt,
            $report->number, $report->year, $report->sequence, $report->signedByPsychologistId, $report->signerName, $report->signerStrNumber,
            $report->signerSilpNumber, $report->signedAt, $now,
        );
    }

    public function update(Report $report): void
    {
        $this->connection->table(self::TABLE)->where('id', $report->id)->update($this->values($report));
    }

    public function nextSequence(int $providerId, int $year): int
    {
        return (int) $this->connection->table(self::TABLE)->where('provider_id', $providerId)->where('report_year', $year)->max('report_sequence') + 1;
    }

    public function invitationIdOf(int $reportId): ?int
    {
        $id = $this->connection->table(self::TABLE.' as reports')
            ->join('assessment_attempts as attempts', 'attempts.id', '=', 'reports.attempt_id')
            ->where('reports.id', $reportId)
            ->value('attempts.invitation_id');

        return $id === null ? null : (int) $id;
    }

    public function pageOfProvider(int $providerId, ?ReportStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE.' as reports')
            ->join('assessment_attempts as attempts', 'attempts.id', '=', 'reports.attempt_id')
            ->join('assessment_invitations as invitations', 'invitations.id', '=', 'attempts.invitation_id')
            ->join('assessment_tests as tests', 'tests.id', '=', 'invitations.test_id')
            ->where('reports.provider_id', $providerId);

        if ($status !== null) {
            $builder->where('reports.status', $status->value);
        }

        $total = (clone $builder)->count();
        $rows = $builder
            ->select(['reports.id', 'reports.status', 'reports.report_number', 'reports.signed_at', 'attempts.invitation_id', 'attempts.submitted_at', 'invitations.applicant_user_id', 'tests.title'])
            ->orderBy('attempts.submitted_at')
            ->orderBy('reports.id')
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get();

        return new Page(array_map(fn (stdClass $row): ReportSummary => new ReportSummary(
            (int) $row->id,
            (int) $row->invitation_id,
            (int) $row->applicant_user_id,
            (string) $row->title,
            ReportStatus::from((string) $row->status),
            $row->report_number,
            $this->time((string) $row->submitted_at),
            $row->signed_at === null ? null : $this->time((string) $row->signed_at),
        ), $rows->all()), $total, $request);
    }

    /** @return array<string, mixed> */
    private function values(Report $report): array
    {
        return [
            'status' => $report->status->value,
            'body' => $report->body,
            'explanation' => $report->explanation,
            'review_notes' => $report->reviewNotes,
            'edited_at' => $report->editedAt,
            'report_number' => $report->number,
            'report_year' => $report->year,
            'report_sequence' => $report->sequence,
            'signed_by_psychologist_id' => $report->signedByPsychologistId,
            'signer_name' => $report->signerName,
            'signer_str_number' => $report->signerStrNumber,
            'signer_silp_number' => $report->signerSilpNumber,
            'signed_at' => $report->signedAt,
            'updated_at' => $this->clock->now(),
        ];
    }

    private function toReport(stdClass $row): Report
    {
        return new Report(
            (int) $row->id,
            (int) $row->attempt_id,
            (int) $row->provider_id,
            ReportStatus::from((string) $row->status),
            (string) $row->body,
            $row->explanation,
            $row->review_notes,
            $row->edited_at === null ? null : $this->time((string) $row->edited_at),
            $row->report_number,
            $row->report_year === null ? null : (int) $row->report_year,
            $row->report_sequence === null ? null : (int) $row->report_sequence,
            $row->signed_by_psychologist_id === null ? null : (int) $row->signed_by_psychologist_id,
            $row->signer_name,
            $row->signer_str_number,
            $row->signer_silp_number,
            $row->signed_at === null ? null : $this->time((string) $row->signed_at),
            $this->time((string) $row->created_at),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
