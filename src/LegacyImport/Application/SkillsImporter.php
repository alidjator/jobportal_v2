<?php

namespace Src\LegacyImport\Application;

use stdClass;

/** Imports `candidate_skill` through the skill master map, in the legacy order, one row per skill, **no limit per user** (decided 2026-10-13: the import keeps what the legacy data has). */
final class SkillsImporter extends CandidateListImporter
{
    public const string ENTITY = 'skills';

    /** @var array<int, array<int, true>> the skills each user got during this run */
    private array $seen = [];

    protected function table(): string
    {
        return 'candidate_skill';
    }

    protected function candidateColumn(): string
    {
        return 'candidate_id';
    }

    protected function itemEntity(): string
    {
        return self::ENTITY;
    }

    protected function kind(): string
    {
        return 'skill';
    }

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [ProfilesImporter::ENTITY, MastersImporter::ENTITY];
    }

    protected function reset(): void
    {
        $this->seen = [];
    }

    protected function lookups(ImportContext $context, array $rows): array
    {
        $skills = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->skill_id === null ? null : (int) $row->skill_id, $rows))));

        return ['skills' => $context->idMap->findMany(MasterCatalog::entityFor('skills'), $skills)];
    }

    protected function map(stdClass $row, int $userId, ImportContext $context, array $lookups): array|string
    {
        $skill = $row->skill_id === null ? null : ($lookups['skills'][(int) $row->skill_id] ?? null);

        if ($skill === null) {
            return 'skill-unmatched';
        }

        if (isset($this->seen[$userId][$skill])) {
            return 'duplicate';
        }

        $this->seen[$userId][$skill] = true;

        return ['skill_id' => $skill, 'position' => $this->countOf($userId) + 1];
    }
}
