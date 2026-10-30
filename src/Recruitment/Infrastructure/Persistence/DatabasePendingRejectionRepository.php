<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\PendingRejection;
use Src\Recruitment\Domain\PendingRejectionRepository;
use Src\Recruitment\Domain\RejectionReason;
use stdClass;

final readonly class DatabasePendingRejectionRepository implements PendingRejectionRepository
{
    private const string TABLE = 'recruitment_pending_rejections';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(PendingRejection $pending): bool
    {
        return $this->connection->table(self::TABLE)->insertOrIgnore([
            'application_id' => $pending->applicationId,
            'stage_id' => $pending->stageId,
            'reason' => $pending->reason->value,
            'note' => $pending->note,
            'requested_by_user_id' => $pending->requestedByUserId,
            'requested_at' => $this->format($pending->requestedAt),
            'commit_at' => $this->format($pending->commitAt),
            'batch_id' => $pending->batchId,
        ]) > 0;
    }

    public function findByApplication(int $applicationId): ?PendingRejection
    {
        $row = $this->connection->table(self::TABLE)->where('application_id', $applicationId)->first();

        return $row === null ? null : $this->toPending($row);
    }

    public function forApplications(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $rows = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('application_id', $applicationIds)->get() as $row) {
            $pending = $this->toPending($row);
            $rows[$pending->applicationId] = $pending;
        }

        return $rows;
    }

    public function exists(int $applicationId): bool
    {
        return $this->connection->table(self::TABLE)->where('application_id', $applicationId)->exists();
    }

    public function undo(int $applicationId, DateTimeImmutable $now): bool
    {
        return $this->connection->table(self::TABLE)->where('application_id', $applicationId)->where('commit_at', '>', $this->format($now))->delete() > 0;
    }

    public function undoBatch(string $batchId, int $companyId, DateTimeImmutable $now): array
    {
        $ids = array_map('intval', $this->connection->table(self::TABLE.' as p')
            ->join('recruitment_applications as a', 'a.id', '=', 'p.application_id')
            ->where('p.batch_id', $batchId)
            ->where('a.company_id', $companyId)
            ->where('p.commit_at', '>', $this->format($now))
            ->pluck('p.application_id')
            ->all());

        if ($ids !== []) {
            $this->connection->table(self::TABLE)->whereIn('application_id', $ids)->where('commit_at', '>', $this->format($now))->delete();
        }

        return $ids;
    }

    public function due(DateTimeImmutable $now, int $limit): array
    {
        return $this->connection->table(self::TABLE)
            ->where('commit_at', '<=', $this->format($now))
            ->orderBy('commit_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (stdClass $row): PendingRejection => $this->toPending($row))
            ->all();
    }

    public function claim(int $id): bool
    {
        return $this->connection->table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    private function toPending(stdClass $row): PendingRejection
    {
        return new PendingRejection(
            (int) $row->application_id,
            (int) $row->stage_id,
            RejectionReason::from((string) $row->reason),
            $row->note === null ? null : (string) $row->note,
            (int) $row->requested_by_user_id,
            $this->time((string) $row->requested_at),
            $this->time((string) $row->commit_at),
            $row->batch_id === null ? null : (string) $row->batch_id,
            (int) $row->id,
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
