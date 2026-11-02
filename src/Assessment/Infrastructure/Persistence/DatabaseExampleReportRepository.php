<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\ExampleReport;
use Src\Assessment\Domain\ExampleReportRepository;

final readonly class DatabaseExampleReportRepository implements ExampleReportRepository
{
    private const string TABLE = 'assessment_example_reports';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $testId): ?ExampleReport
    {
        $row = $this->connection->table(self::TABLE)->where('test_id', $testId)->first();

        return $row === null ? null : new ExampleReport(
            (string) $row->path,
            (string) $row->original_name,
            (int) $row->size_bytes,
            new DateTimeImmutable((string) $row->uploaded_at, new DateTimeZone('UTC')),
        );
    }

    public function save(int $testId, ExampleReport $example): void
    {
        $this->connection->table(self::TABLE)->upsert(
            [['test_id' => $testId, 'path' => $example->path, 'original_name' => $example->originalName, 'size_bytes' => $example->sizeBytes, 'uploaded_at' => $example->uploadedAt]],
            ['test_id'],
            ['path', 'original_name', 'size_bytes', 'uploaded_at'],
        );
    }

    public function delete(int $testId): void
    {
        $this->connection->table(self::TABLE)->where('test_id', $testId)->delete();
    }
}
