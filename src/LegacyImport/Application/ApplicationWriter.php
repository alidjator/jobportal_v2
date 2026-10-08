<?php

namespace Src\LegacyImport\Application;

/** Writes applications, their stage history and extra stages into the tables of the Recruitment context (the documented exception of this context). */
interface ApplicationWriter
{
    /** @return array<string, int> the ids of the company's default stages by stage type */
    public function defaultStages(int $companyId): array;

    /** @return array{id: int, type: string}|null the company's stage with this name (letter case ignored) */
    public function stageByName(int $companyId, string $name): ?array;

    public function stageCount(int $companyId): int;

    /** An extra stage, placed after the other active stages and before the terminal ones. @return int the id of the new stage */
    public function insertStage(int $companyId, string $type, string $name, string $createdAt): int;

    /** Whether the user already has a live (not withdrawn) application to the job. */
    public function hasLiveApplication(int $userId, int $jobId): bool;

    /** Whether the user has a profile in the new schema. */
    public function hasProfile(int $userId): bool;

    /**
     * @param  array<string, mixed>  $columns
     * @return int the id of the new application
     */
    public function insertApplication(array $columns): int;

    public function insertHistory(int $applicationId, ?int $fromStageId, int $toStageId, ?string $fromType, string $toType, string $actorKind, ?int $actorId, string $createdAt): void;
}
