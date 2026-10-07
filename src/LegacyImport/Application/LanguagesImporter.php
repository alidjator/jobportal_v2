<?php

namespace Src\LegacyImport\Application;

use stdClass;

/**
 * Imports `candidate_language` through the language master map (the legacy languages are free names, matched by name). Legacy has no level, so each is
 * imported as `conversational`, a neutral middle value the owner can change. One row per language, at most 10 per user. A language with no match is skipped.
 */
final class LanguagesImporter extends CandidateListImporter
{
    public const string ENTITY = 'languages';

    public const string PROFICIENCY = 'conversational';

    private const int LIMIT = 10;

    /** @var array<int, array<int, true>> the languages each user got during this run */
    private array $seen = [];

    protected function table(): string
    {
        return 'candidate_language';
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
        return 'language';
    }

    protected function limit(): int
    {
        return self::LIMIT;
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
        $languages = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->candidate_language_id, $rows)));

        return ['languages' => $context->idMap->findMany(MasterCatalog::entityFor('languages'), $languages)];
    }

    protected function map(stdClass $row, int $userId, ImportContext $context, array $lookups): array|string
    {
        $language = $lookups['languages'][(int) $row->candidate_language_id] ?? null;

        if ($language === null) {
            return 'language-unmatched';
        }

        if (isset($this->seen[$userId][$language])) {
            return 'duplicate';
        }

        $this->seen[$userId][$language] = true;

        return ['language_id' => $language, 'proficiency' => self::PROFICIENCY];
    }
}
