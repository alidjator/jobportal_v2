<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\ScreeningWriter;

final readonly class DatabaseScreeningWriter implements ScreeningWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function insertQuestion(int $companyId, string $text, int $creatorUserId, ?string $archivedAt, string $createdAt, string $updatedAt): int
    {
        return (int) $this->connection->table('recruitment_screening_questions')->insertGetId([
            'company_id' => $companyId,
            'type' => 'text',
            'text' => $text,
            'archived_at' => $archivedAt,
            'created_by_user_id' => $creatorUserId,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }

    public function attachedCount(int $jobId): int
    {
        return $this->connection->table('recruitment_job_questions')->where('job_id', $jobId)->count();
    }

    public function isAttached(int $jobId, int $questionId): bool
    {
        return $this->connection->table('recruitment_job_questions')->where('job_id', $jobId)->where('question_id', $questionId)->exists();
    }

    public function attach(int $jobId, int $questionId, int $position, bool $required, string $createdAt): int
    {
        return (int) $this->connection->table('recruitment_job_questions')->insertGetId([
            'job_id' => $jobId,
            'question_id' => $questionId,
            'position' => $position,
            'is_required' => $required,
            'is_must_have' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
