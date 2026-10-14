<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\ReportTemplateRepository;

final readonly class DatabaseReportTemplateRepository implements ReportTemplateRepository
{
    private const string TABLE = 'assessment_report_templates';

    public function __construct(private ConnectionInterface $connection) {}

    public function bodyOf(int $testId): ?string
    {
        $body = $this->connection->table(self::TABLE)->where('test_id', $testId)->value('body');

        return $body === null ? null : (string) $body;
    }

    public function save(int $testId, string $body, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->upsert([['test_id' => $testId, 'body' => $body, 'updated_at' => $now]], ['test_id'], ['body', 'updated_at']);
    }

    public function explanationTemplateOf(int $testId): ?string
    {
        $body = $this->connection->table(self::TABLE)->where('test_id', $testId)->value('explanation_template');

        return $body === null ? null : (string) $body;
    }

    public function saveExplanationTemplate(int $testId, string $body, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->upsert([['test_id' => $testId, 'explanation_template' => $body, 'updated_at' => $now]], ['test_id'], ['explanation_template', 'updated_at']);
    }
}
