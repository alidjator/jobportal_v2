<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\ApplicationWriter;

final readonly class DatabaseApplicationWriter implements ApplicationWriter
{
    private const array TERMINAL_TYPES = ['rejected', 'withdrawn'];

    public function __construct(private ConnectionInterface $connection) {}

    public function defaultStages(int $companyId): array
    {
        return $this->connection->table('recruitment_stages')
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->pluck('id', 'type')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function stageByName(int $companyId, string $name): ?array
    {
        // The column is case-insensitive, so the comparison is too.
        $row = $this->connection->table('recruitment_stages')->where('company_id', $companyId)->where('name', $name)->first(['id', 'type']);

        return $row === null ? null : ['id' => (int) $row->id, 'type' => (string) $row->type];
    }

    public function insertStage(int $companyId, string $type, string $name, string $createdAt): int
    {
        $stages = $this->connection->table('recruitment_stages')->where('company_id', $companyId)->orderBy('position')->get(['id', 'type', 'position']);
        $insertAt = $stages->count();

        foreach ($stages->values() as $index => $stage) {
            if (in_array($stage->type, self::TERMINAL_TYPES, true)) {
                $insertAt = $index;

                break;
            }
        }

        foreach ($stages->slice($insertAt) as $stage) {
            $this->connection->table('recruitment_stages')->where('id', $stage->id)->update(['position' => (int) $stage->position + 1]);
        }

        return (int) $this->connection->table('recruitment_stages')->insertGetId([
            'company_id' => $companyId,
            'type' => $type,
            'name' => $name,
            'position' => $insertAt + 1,
            'is_default' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function hasLiveApplication(int $userId, int $jobId): bool
    {
        return $this->connection->table('recruitment_applications')->where('active_key', $userId.'-'.$jobId)->exists();
    }

    public function hasProfile(int $userId): bool
    {
        return $this->connection->table('jobseeker_profiles')->where('user_id', $userId)->exists();
    }

    public function insertApplication(array $columns): int
    {
        return (int) $this->connection->table('recruitment_applications')->insertGetId($columns);
    }

    public function insertHistory(int $applicationId, ?int $fromStageId, int $toStageId, ?string $fromType, string $toType, string $actorKind, ?int $actorId, string $createdAt): void
    {
        $this->connection->table('recruitment_application_stage_history')->insert([
            'application_id' => $applicationId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'from_type' => $fromType,
            'to_type' => $toType,
            'actor_kind' => $actorKind,
            'actor_id' => $actorId,
            'created_at' => $createdAt,
        ]);
    }
}
