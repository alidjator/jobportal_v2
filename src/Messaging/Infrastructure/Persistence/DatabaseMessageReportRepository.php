<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Domain\MessageReport;
use Src\Messaging\Domain\MessageReportRepository;
use Src\Messaging\Domain\ReporterSide;
use Src\Messaging\Domain\ReportReason;
use Src\Messaging\Domain\ReportStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

final readonly class DatabaseMessageReportRepository implements MessageReportRepository
{
    private const string TABLE = 'messaging_reports';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function hasOpenReport(int $conversationId, int $reporterUserId): bool
    {
        return $this->connection->table(self::TABLE)
            ->where('conversation_id', $conversationId)->where('reporter_user_id', $reporterUserId)->where('status', ReportStatus::Open->value)
            ->exists();
    }

    public function add(int $conversationId, ?int $messageId, ReporterSide $side, int $reporterUserId, ReportReason $reason, ?string $note, array $snapshot, DateTimeImmutable $at): MessageReport
    {
        $time = $this->format($at);

        $id = (int) $this->connection->table(self::TABLE)->insertGetId([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'reporter_side' => $side->value,
            'reporter_user_id' => $reporterUserId,
            'reason' => $reason->value,
            'note' => $note,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'status' => ReportStatus::Open->value,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return new MessageReport($id, $conversationId, $messageId, $side, $reporterUserId, $reason, $note, $snapshot, ReportStatus::Open, null, null, null, $at);
    }

    public function find(int $id, bool $forUpdate = false): ?MessageReport
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toReport($row);
    }

    public function page(?ReportStatus $status, PageRequest $request): Page
    {
        $query = $this->connection->table(self::TABLE);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'open' THEN created_at END ASC")
            ->orderByRaw("CASE WHEN status = 'open' THEN id END ASC")
            ->orderByDesc('decided_at')->orderByDesc('id')
            ->offset($request->offset())->limit($request->perPage)->get();

        return new Page($rows->map(fn (object $row): MessageReport => $this->toReport($row))->all(), $total, $request);
    }

    public function decide(int $id, ReportStatus $status, int $adminId, ?string $note, DateTimeImmutable $at): void
    {
        $time = $this->format($at);

        $this->connection->table(self::TABLE)->where('id', $id)->update([
            'status' => $status->value,
            'decided_by_admin_id' => $adminId,
            'decided_at' => $time,
            'decision_note' => $note,
            'updated_at' => $time,
        ]);
    }

    public function clearSnapshotsDecidedBefore(DateTimeImmutable $cutoff): int
    {
        return $this->connection->table(self::TABLE)
            ->whereNotNull('snapshot')->where('status', '!=', ReportStatus::Open->value)->where('decided_at', '<', $this->format($cutoff))
            ->update(['snapshot' => null]);
    }

    public function eraseUser(int $userId): void
    {
        $this->connection->table(self::TABLE)->where('reporter_user_id', $userId)->update(['reporter_user_id' => null, 'note' => null, 'snapshot' => null]);

        $this->connection->table(self::TABLE)
            ->whereIn('conversation_id', $this->connection->table('messaging_conversations')->where('job_seeker_user_id', $userId)->select('id'))
            ->update(['snapshot' => null]);
    }

    private function toReport(object $row): MessageReport
    {
        /** @var list<array<string, mixed>>|null $snapshot */
        $snapshot = $row->snapshot === null ? null : json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new MessageReport(
            (int) $row->id,
            (int) $row->conversation_id,
            $row->message_id === null ? null : (int) $row->message_id,
            ReporterSide::from((string) $row->reporter_side),
            $row->reporter_user_id === null ? null : (int) $row->reporter_user_id,
            ReportReason::from((string) $row->reason),
            $row->note === null ? null : (string) $row->note,
            $snapshot,
            ReportStatus::from((string) $row->status),
            $row->decided_by_admin_id === null ? null : (int) $row->decided_by_admin_id,
            $row->decided_at === null ? null : $this->time((string) $row->decided_at),
            $row->decision_note === null ? null : (string) $row->decision_note,
            $this->time((string) $row->created_at),
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
