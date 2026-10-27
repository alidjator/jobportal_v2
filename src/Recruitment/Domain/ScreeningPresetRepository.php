<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface ScreeningPresetRepository
{
    /** The preset with its questions in their order. */
    public function find(int $id): ?ScreeningPreset;

    /**
     * The presets with these ids, with their questions, keyed by id; an id without a preset is missing.
     *
     * @param  list<int>  $ids
     * @return array<int, ScreeningPreset>
     */
    public function findMany(array $ids): array;

    /** Whether the company already has a preset with this name, ignoring letter case; the preset `$exceptId` is not counted. */
    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool;

    /**
     * @param  list<JobQuestionDraft>  $drafts  in their order
     * @return int the id of the new preset
     */
    public function create(int $companyId, string $name, ?int $jobLevelId, int $createdByUserId, array $drafts, DateTimeImmutable $at): int;

    /**
     * Replaces the name, the level hint and the whole ordered list of questions.
     *
     * @param  list<JobQuestionDraft>  $drafts  in their order
     */
    public function update(int $id, string $name, ?int $jobLevelId, array $drafts, DateTimeImmutable $at): void;

    public function delete(int $id): void;
}
