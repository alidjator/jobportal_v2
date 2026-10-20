<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\ImportSummary;
use Src\Company\Domain\ImportSummaryRepository;

final readonly class DatabaseImportSummaryRepository implements ImportSummaryRepository
{
    private const string TABLE = 'company_import_summaries';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $companyId): ?ImportSummary
    {
        $row = $this->connection->table(self::TABLE)->where('company_id', $companyId)->first();

        return $row === null ? null : new ImportSummary(
            (int) $row->company_id,
            json_decode((string) $row->items, true, 512, JSON_THROW_ON_ERROR),
            $this->time((string) $row->created_at),
            $row->dismissed_at === null ? null : $this->time((string) $row->dismissed_at),
        );
    }

    public function dismiss(int $companyId, int $byUserId, DateTimeImmutable $at): bool
    {
        $this->connection->table(self::TABLE)->where('company_id', $companyId)->whereNull('dismissed_at')->update([
            'dismissed_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'dismissed_by_user_id' => $byUserId,
        ]);

        return $this->connection->table(self::TABLE)->where('company_id', $companyId)->exists();
    }

    public function companyIdsAfter(int $afterId, int $limit): array
    {
        return $this->connection->table(self::TABLE)->where('company_id', '>', $afterId)->orderBy('company_id')->limit($limit)->pluck('company_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public function deleteOfCompanies(array $companyIds): void
    {
        $this->connection->table(self::TABLE)->whereIn('company_id', $companyIds)->delete();
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
