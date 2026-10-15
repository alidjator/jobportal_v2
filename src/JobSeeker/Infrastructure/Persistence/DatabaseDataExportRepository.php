<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\DataExport;
use Src\JobSeeker\Domain\DataExportRepository;
use Src\JobSeeker\Domain\DataExportStatus;
use stdClass;

final readonly class DatabaseDataExportRepository implements DataExportRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function latestOf(int $userId): ?DataExport
    {
        $row = $this->connection->table('jobseeker_data_exports')->where('user_id', $userId)->orderByDesc('created_at')->orderByDesc('id')->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function find(int $id): ?DataExport
    {
        $row = $this->connection->table('jobseeker_data_exports')->where('id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function start(int $userId, DateTimeImmutable $now): DataExport
    {
        $time = $now->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('jobseeker_data_exports')->insertGetId([
            'user_id' => $userId, 'status' => DataExportStatus::Building->value, 'created_at' => $time, 'updated_at' => $time,
        ]);

        return new DataExport($id, $userId, DataExportStatus::Building, null, null, null, null, $now);
    }

    public function markReady(int $id, string $path, int $sizeBytes, DateTimeImmutable $readyAt, DateTimeImmutable $expiresAt): void
    {
        $this->connection->table('jobseeker_data_exports')->where('id', $id)->update([
            'status' => DataExportStatus::Ready->value, 'path' => $path, 'size_bytes' => $sizeBytes, 'error' => null,
            'ready_at' => $readyAt->format(self::TIME_FORMAT), 'expires_at' => $expiresAt->format(self::TIME_FORMAT), 'updated_at' => $readyAt->format(self::TIME_FORMAT),
        ]);
    }

    public function markFailed(int $id, string $error, DateTimeImmutable $at): void
    {
        $this->connection->table('jobseeker_data_exports')->where('id', $id)->update([
            'status' => DataExportStatus::Failed->value, 'error' => mb_substr($error, 0, 255), 'updated_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function due(DateTimeImmutable $now, int $limit): array
    {
        $staleBefore = $now->modify('-'.DataExport::STALE_AFTER_MINUTES.' minutes')->format(self::TIME_FORMAT);

        return $this->connection->table('jobseeker_data_exports')
            ->where(fn ($query) => $query
                ->where(fn ($ready) => $ready->where('status', DataExportStatus::Ready->value)->where('expires_at', '<=', $now->format(self::TIME_FORMAT)))
                ->orWhere(fn ($building) => $building->where('status', DataExportStatus::Building->value)->where('created_at', '<=', $staleBefore)))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (stdClass $row): DataExport => self::hydrate($row))
            ->all();
    }

    public function markExpired(int $id, DateTimeImmutable $at): void
    {
        $this->connection->table('jobseeker_data_exports')->where('id', $id)->update([
            'status' => DataExportStatus::Expired->value, 'path' => null, 'updated_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function ofUser(int $userId): array
    {
        return $this->connection->table('jobseeker_data_exports')->where('user_id', $userId)->orderBy('id')->get()
            ->map(static fn (stdClass $row): DataExport => self::hydrate($row))
            ->all();
    }

    public function deleteOfUser(int $userId): void
    {
        $this->connection->table('jobseeker_data_exports')->where('user_id', $userId)->delete();
    }

    private static function hydrate(stdClass $row): DataExport
    {
        $utc = new DateTimeZone('UTC');
        $time = static fn (?string $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable($value, $utc);

        return new DataExport(
            (int) $row->id, (int) $row->user_id, DataExportStatus::from($row->status), $row->path, $row->size_bytes === null ? null : (int) $row->size_bytes,
            $time($row->ready_at), $time($row->expires_at), new DateTimeImmutable($row->created_at, $utc),
        );
    }
}
