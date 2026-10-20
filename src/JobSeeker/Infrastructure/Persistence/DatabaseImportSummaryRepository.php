<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\ImportSummary;
use Src\JobSeeker\Domain\ImportSummaryRepository;

final readonly class DatabaseImportSummaryRepository implements ImportSummaryRepository
{
    private const string TABLE = 'jobseeker_import_summaries';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $userId): ?ImportSummary
    {
        $row = $this->connection->table(self::TABLE)->where('user_id', $userId)->first();

        return $row === null ? null : new ImportSummary(
            (int) $row->user_id,
            json_decode((string) $row->items, true, 512, JSON_THROW_ON_ERROR),
            $this->time((string) $row->created_at),
            $row->dismissed_at === null ? null : $this->time((string) $row->dismissed_at),
        );
    }

    public function dismiss(int $userId, DateTimeImmutable $at): bool
    {
        $this->connection->table(self::TABLE)->where('user_id', $userId)->whereNull('dismissed_at')->update(['dismissed_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);

        return $this->connection->table(self::TABLE)->where('user_id', $userId)->exists();
    }

    public function userIdsAfter(int $afterId, int $limit): array
    {
        return $this->connection->table(self::TABLE)->where('user_id', '>', $afterId)->orderBy('user_id')->limit($limit)->pluck('user_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public function deleteOfUsers(array $userIds): void
    {
        $this->connection->table(self::TABLE)->whereIn('user_id', $userIds)->delete();
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
