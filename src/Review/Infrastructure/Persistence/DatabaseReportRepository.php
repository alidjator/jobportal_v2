<?php

namespace Src\Review\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Review\Domain\Report;
use Src\Review\Domain\ReporterKind;
use Src\Review\Domain\ReportReason;
use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\ReportStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseReportRepository implements ReportRepository
{
    private const string TABLE = 'review_reports';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(Report $report): Report
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([
                'review_id' => $report->reviewId,
                'reporter_kind' => $report->reporterKind->value,
                'reporter_id' => $report->reporterId,
                'reason' => $report->reason->value,
                'note' => $report->note,
                'status' => $report->status->value,
                'created_at' => $report->createdAt->format(self::TIME_FORMAT),
                'updated_at' => $report->createdAt->format(self::TIME_FORMAT),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('You already reported this review.', 'already-reported');
        }

        return new Report($report->reviewId, $report->reporterKind, $report->reporterId, $report->reason, $report->note, $report->status, null, null, $report->createdAt, $id);
    }

    public function hasOpen(int $reviewId, ReporterKind $kind, int $reporterId): bool
    {
        return $this->connection->table(self::TABLE)
            ->where('review_id', $reviewId)->where('reporter_kind', $kind->value)->where('reporter_id', $reporterId)->where('status', ReportStatus::Open->value)
            ->exists();
    }

    public function update(Report $report): void
    {
        $this->connection->table(self::TABLE)->where('id', $report->id)->update([
            'status' => $report->status->value,
            'resolved_by_admin_id' => $report->resolvedByAdminId,
            'resolved_at' => $report->resolvedAt?->format(self::TIME_FORMAT),
            'updated_at' => ($report->resolvedAt ?? $report->createdAt)->format(self::TIME_FORMAT),
        ]);
    }

    public function find(int $reportId, bool $forUpdate = false): ?Report
    {
        $query = $this->connection->table(self::TABLE)->where('id', $reportId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toReport($row);
    }

    public function forReview(int $reviewId): array
    {
        return array_map(fn (stdClass $row): Report => $this->toReport($row), $this->connection->table(self::TABLE)->where('review_id', $reviewId)->orderByDesc('id')->get()->all());
    }

    public function openCounts(array $reviewIds): array
    {
        if ($reviewIds === []) {
            return [];
        }

        $counts = [];

        foreach ($this->connection->table(self::TABLE)->where('status', ReportStatus::Open->value)->whereIn('review_id', $reviewIds)
            ->selectRaw('review_id, count(*) as total')->groupBy('review_id')->get() as $row) {
            $counts[(int) $row->review_id] = (int) $row->total;
        }

        return $counts;
    }

    public function resolveOpen(int $reviewId, ReportStatus $status, int $adminId, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('review_id', $reviewId)->where('status', ReportStatus::Open->value)->update([
            'status' => $status->value,
            'resolved_by_admin_id' => $adminId,
            'resolved_at' => $now->format(self::TIME_FORMAT),
            'updated_at' => $now->format(self::TIME_FORMAT),
        ]);
    }

    public function deleteOfUser(int $userId): void
    {
        $this->connection->table(self::TABLE)->where('reporter_kind', ReporterKind::User->value)->where('reporter_id', $userId)->delete();
    }

    public function page(ReportStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)->where('status', $status->value);
        $total = $builder->count();
        $rows = $builder->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Report => $this->toReport($row), $rows->all()), $total, $request);
    }

    private function toReport(stdClass $row): Report
    {
        return new Report(
            (int) $row->review_id,
            ReporterKind::from((string) $row->reporter_kind),
            (int) $row->reporter_id,
            ReportReason::from((string) $row->reason),
            $row->note === null ? null : (string) $row->note,
            ReportStatus::from((string) $row->status),
            $row->resolved_by_admin_id === null ? null : (int) $row->resolved_by_admin_id,
            $row->resolved_at === null ? null : new DateTimeImmutable((string) $row->resolved_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
