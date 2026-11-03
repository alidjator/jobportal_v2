<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use LogicException;
use Src\Assessment\Domain\Recommendation;
use Src\Assessment\Domain\RegisterEntry;
use Src\Assessment\Domain\RegisterFilter;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportStatus;
use Src\Assessment\Domain\ReportSummary;
use Src\Assessment\Domain\ReportVerification;
use Src\Assessment\Domain\StoredReportPdf;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseReportRepository implements ReportRepository
{
    private const string TABLE = 'assessment_reports';

    private const string REVISIONS = 'assessment_report_revisions';

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

        return $this->find((int) $id) ?? throw new LogicException('The new report was not found.');
    }

    public function update(Report $report): void
    {
        $this->connection->table(self::TABLE)->where('id', $report->id)->update($this->values($report));
    }

    public function keepVersion(Report $report, DateTimeImmutable $supersededAt): void
    {
        $now = $this->clock->now();
        $stored = $this->connection->table(self::TABLE)->where('id', $report->id)->first(['pdf_path', 'pdf_sha256']);

        $this->connection->table(self::REVISIONS)->insert([
            'report_id' => $report->id,
            'revision' => $report->revision,
            'report_number' => (string) $report->number,
            'body' => $report->body,
            'explanation' => $report->explanation,
            'signed_by_psychologist_id' => $report->signedByPsychologistId,
            'signer_name' => $report->signerName,
            'signer_str_number' => $report->signerStrNumber,
            'signer_silp_number' => $report->signerSilpNumber,
            'recommendation' => $report->recommendation?->value,
            'signed_at' => $report->signedAt,
            'superseded_at' => $supersededAt,
            'pdf_path' => $stored?->pdf_path,
            'pdf_sha256' => $stored?->pdf_sha256,
            'provider_identity' => $report->providerIdentity === null ? null : json_encode($report->providerIdentity, JSON_THROW_ON_ERROR),
            'revision_reason' => $report->revisionReason,
            'signer_signature_path' => $report->signerSignaturePath,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // The revision has its own number and text, so its PDF is rendered again: the file of the replaced version stays with its revision row.
        $this->connection->table(self::TABLE)->where('id', $report->id)->update(['pdf_path' => null, 'pdf_sha256' => null]);
    }

    public function storedPdf(int $reportId): ?StoredReportPdf
    {
        $row = $this->connection->table(self::TABLE)->where('id', $reportId)->first(['pdf_path', 'pdf_sha256']);

        return $row === null || $row->pdf_path === null || $row->pdf_sha256 === null ? null : new StoredReportPdf((string) $row->pdf_path, (string) $row->pdf_sha256);
    }

    public function storePdf(int $reportId, StoredReportPdf $pdf): void
    {
        $this->connection->table(self::TABLE)->where('id', $reportId)->update(['pdf_path' => $pdf->path, 'pdf_sha256' => $pdf->sha256]);
    }

    public function verification(string $number): ?ReportVerification
    {
        $base = (string) preg_replace('/-R\d+$/', '', $number);
        $query = $this->connection->table(self::TABLE.' as reports')
            ->join('assessment_providers as providers', 'providers.id', '=', 'reports.provider_id')
            ->where('reports.status', ReportStatus::Signed->value)
            ->where(fn ($q) => $q->where('reports.report_number', $base)->orWhere('reports.report_number', 'like', $base.'-R%'));
        $row = $query->first(['reports.id', 'reports.report_number', 'reports.revision', 'reports.signer_name', 'reports.signed_at', 'reports.body', 'reports.pdf_sha256', 'providers.name as provider_name', 'providers.code as provider_code']);

        if ($row === null) {
            return null;
        }

        // The latest revision is the report row; an earlier one is a row of the revisions.
        if ($number === $base || $number === (string) $row->report_number) {
            return new ReportVerification(
                (string) $row->provider_name, (string) $row->provider_code, (string) $row->signer_name, $this->time((string) $row->signed_at), (int) $row->revision, false,
                $row->pdf_sha256 === null ? null : (string) $row->pdf_sha256, (string) $row->body === '',
            );
        }

        $old = $this->connection->table(self::REVISIONS)->where('report_id', $row->id)->where('report_number', $number)->first(['revision', 'signer_name', 'signed_at', 'body', 'pdf_sha256']);

        return $old === null ? null : new ReportVerification(
            (string) $row->provider_name, (string) $row->provider_code, (string) $old->signer_name, $this->time((string) $old->signed_at), (int) $old->revision, true,
            $old->pdf_sha256 === null ? null : (string) $old->pdf_sha256, (string) $old->body === '',
        );
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

    public function releaseClaimsOf(int $psychologistId): void
    {
        $this->connection->table(self::TABLE)->where('claimed_by_psychologist_id', $psychologistId)->update(['claimed_by_psychologist_id' => null, 'claimed_at' => null, 'claim_touched_at' => null]);
    }

    public function pageOfProvider(int $providerId, ?ReportStatus $status, ?bool $reviewed, PageRequest $request, ?string $claimed = null, ?int $psychologistId = null): Page
    {
        $builder = $this->connection->table(self::TABLE.' as reports')
            ->join('assessment_attempts as attempts', 'attempts.id', '=', 'reports.attempt_id')
            ->join('assessment_invitations as invitations', 'invitations.id', '=', 'attempts.invitation_id')
            ->join('assessment_tests as tests', 'tests.id', '=', 'invitations.test_id')
            ->where('reports.provider_id', $providerId);

        if ($status !== null) {
            $builder->where('reports.status', $status->value);
        }

        if ($reviewed !== null) {
            // A report signed by hand is reviewed by the signature itself; only an automatic one waits for a review.
            if ($reviewed) {
                $builder->where(fn ($query) => $query->where('reports.auto_issued', false)->orWhereNotNull('reports.reviewed_at'));
            } else {
                $builder->where('reports.auto_issued', true)->whereNull('reports.reviewed_at');
            }
        }

        if ($claimed !== null) {
            $live = $this->clock->now()->modify(Report::CLAIM_LAPSE);
            $hold = static fn ($query) => $query->whereNotNull('reports.claimed_by_psychologist_id')->where('reports.claim_touched_at', '>', $live);

            match ($claimed) {
                'mine' => $builder->where($hold)->where('reports.claimed_by_psychologist_id', $psychologistId),
                'others' => $builder->where($hold)->where('reports.claimed_by_psychologist_id', '!=', $psychologistId),
                default => $builder->whereNot($hold),
            };
        }

        $total = (clone $builder)->count();
        $rows = $builder
            ->select(['reports.id', 'reports.status', 'reports.report_number', 'reports.signed_at', 'reports.auto_issued', 'reports.reviewed_at', 'reports.claimed_by_psychologist_id', 'reports.claim_touched_at', 'attempts.invitation_id', 'attempts.submitted_at', 'invitations.applicant_user_id', 'invitations.job_id', 'invitations.company_id', 'tests.title'])
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
            (bool) $row->auto_issued,
            $row->reviewed_at === null ? null : $this->time((string) $row->reviewed_at),
            (int) $row->job_id,
            (int) $row->company_id,
            $row->claimed_by_psychologist_id === null ? null : (int) $row->claimed_by_psychologist_id,
            $row->claim_touched_at === null ? null : $this->time((string) $row->claim_touched_at),
        ), $rows->all()), $total, $request);
    }

    public function registerOfProvider(int $providerId, RegisterFilter $filter, PageRequest $request): Page
    {
        $builder = $this->registerQuery($providerId, $filter);
        $total = (clone $builder)->count();
        $rows = $builder->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map($this->toEntry(...), $rows->all()), $total, $request);
    }

    public function registerCount(int $providerId, RegisterFilter $filter): int
    {
        return $this->registerQuery($providerId, $filter)->count();
    }

    public function registerRows(int $providerId, RegisterFilter $filter, int $limit): iterable
    {
        foreach ($this->registerQuery($providerId, $filter)->limit($limit)->cursor() as $row) {
            yield $this->toEntry($row);
        }
    }

    /** The signed reports of the provider, narrowed by the filter, newest signature first. */
    private function registerQuery(int $providerId, RegisterFilter $filter): Builder
    {
        $builder = $this->connection->table(self::TABLE.' as reports')
            ->join('assessment_attempts as attempts', 'attempts.id', '=', 'reports.attempt_id')
            ->join('assessment_invitations as invitations', 'invitations.id', '=', 'attempts.invitation_id')
            ->join('assessment_tests as tests', 'tests.id', '=', 'invitations.test_id')
            ->where('reports.provider_id', $providerId)
            ->where('reports.status', ReportStatus::Signed->value);

        if ($filter->from !== null) {
            $builder->where('reports.signed_at', '>=', AnalyticsDays::startUtc($filter->from));
        }

        if ($filter->to !== null) {
            $builder->where('reports.signed_at', '<', AnalyticsDays::endUtc($filter->to));
        }

        if ($filter->psychologistId !== null) {
            $builder->where('reports.signed_by_psychologist_id', $filter->psychologistId);
        }

        if ($filter->testId !== null) {
            $builder->where('invitations.test_id', $filter->testId);
        }

        return $builder
            ->select(['reports.id', 'reports.report_number', 'reports.revision', 'reports.signed_at', 'reports.signed_by_psychologist_id', 'reports.signer_name', 'reports.auto_issued', 'reports.reviewed_at', 'reports.recommendation', 'reports.body', 'invitations.test_id', 'invitations.company_id', 'tests.title'])
            ->orderByDesc('reports.signed_at')
            ->orderByDesc('reports.id');
    }

    private function toEntry(stdClass $row): RegisterEntry
    {
        return new RegisterEntry(
            (int) $row->id,
            (string) $row->report_number,
            (int) $row->revision,
            $this->time((string) $row->signed_at),
            $row->signed_by_psychologist_id === null ? null : (int) $row->signed_by_psychologist_id,
            $row->signer_name,
            (int) $row->test_id,
            (string) $row->title,
            $row->company_id === null ? null : (int) $row->company_id,
            (bool) $row->auto_issued,
            $row->reviewed_at === null ? null : $this->time((string) $row->reviewed_at),
            $row->recommendation === null ? null : Recommendation::from((string) $row->recommendation),
            (string) $row->body === '',
        );
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
            'auto_issued' => $report->autoIssued,
            'reviewed_at' => $report->reviewedAt,
            'reviewed_by_psychologist_id' => $report->reviewedByPsychologistId,
            'revision' => $report->revision,
            'claimed_by_psychologist_id' => $report->claimedByPsychologistId,
            'claimed_at' => $report->claimedAt,
            'claim_touched_at' => $report->claimTouchedAt,
            'recommendation' => $report->recommendation?->value,
            'provider_identity' => $report->providerIdentity === null ? null : json_encode($report->providerIdentity, JSON_THROW_ON_ERROR),
            'revision_reason' => $report->revisionReason,
            'signer_signature_path' => $report->signerSignaturePath,
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
            (bool) $row->auto_issued,
            $row->reviewed_at === null ? null : $this->time((string) $row->reviewed_at),
            $row->reviewed_by_psychologist_id === null ? null : (int) $row->reviewed_by_psychologist_id,
            (int) $row->revision,
            $row->claimed_by_psychologist_id === null ? null : (int) $row->claimed_by_psychologist_id,
            $row->claimed_at === null ? null : $this->time((string) $row->claimed_at),
            $row->claim_touched_at === null ? null : $this->time((string) $row->claim_touched_at),
            $row->recommendation === null ? null : Recommendation::from((string) $row->recommendation),
            $row->provider_identity === null ? null : (array) json_decode((string) $row->provider_identity, true, 512, JSON_THROW_ON_ERROR),
            $row->revision_reason,
            $row->signer_signature_path,
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
