<?php

namespace Src\LegacyImport\Application;

use DateTimeZone;

/**
 * Works out which new master each legacy master item became (see `MasterMatcher`) and remembers it in the id map under `master.<key>`, so every later importer
 * finds its masters there. Unlike the other importers it is worked out again at each run (a corrected mapping file takes effect); the items without a
 * counterpart are listed in the report by name (configuration, not personal data). For the masters that `createsMissing` (skills, job roles, job
 * categories) an item with no counterpart becomes a new active master at a real run instead; items with the same normalised name become one master.
 * A dry run reports what would be created (note `created`) and writes nothing.
 */
final readonly class MastersImporter implements Importer
{
    public const string ENTITY = 'masters';

    private const int NAME_MAX = 255;

    public function __construct(private MasterMatcher $matcher, private MasterWriter $writer) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [];
    }

    public function run(ImportContext $context): void
    {
        $overrides = $context->mapping(self::ENTITY);

        foreach (MasterCatalog::all() as $spec) {
            $entity = MasterCatalog::entityFor($spec->key);
            $match = $this->matcher->match($spec, $this->overridesFor($overrides, $spec->key));
            $matched = $match->matched;
            $unmatched = $match->unmatched;
            $coveredByNew = 0;

            if ($spec->createsMissing) {
                [$creatable, $unmatched] = $this->splitCreatable($unmatched);
                $groups = $this->groupByName($creatable);

                foreach ($groups as $group) {
                    $newId = $context->write ? $this->writer->create($spec, $group['name'], $spec->key === 'skills' ? SkillKindGuesser::guess($group['name']) : null, $this->timestamp($context)) : null;

                    foreach ($group['legacy_ids'] as $legacyId) {
                        $coveredByNew++;

                        if ($newId !== null) {
                            $matched[$legacyId] = $newId;
                        }
                    }
                }

                if ($groups !== []) {
                    $context->report->note($entity, 'created', count($groups));
                }
            }

            $context->report->read($entity, count($match->matched) + count($match->unmatched));
            $context->report->imported($entity, count($match->matched) + $coveredByNew);

            foreach ($unmatched as $item) {
                $context->report->skipped($entity, $item['legacy_id'], $item['reason']);
                $context->report->unmatched($entity, ['legacy_id' => $item['legacy_id'], 'names' => $item['names'], 'reason' => $item['reason']]);
            }

            if ($context->write) {
                $context->idMap->replace($entity, $matched, $context->clock->now());
            }
        }
    }

    /**
     * Only an item that found no counterpart is created; an ambiguous one or one whose mapped slug is unknown stays reported (a human decision).
     *
     * @param  list<array{legacy_id: int, names: list<string>, reason: string}>  $unmatched
     * @return array{0: list<array{legacy_id: int, names: list<string>, reason: string}>, 1: list<array{legacy_id: int, names: list<string>, reason: string}>}
     */
    private function splitCreatable(array $unmatched): array
    {
        $creatable = [];
        $rest = [];

        foreach ($unmatched as $item) {
            if ($item['reason'] === 'no-match' && $this->displayName($item['names']) !== '') {
                $creatable[] = $item;
            } else {
                $rest[] = $item;
            }
        }

        return [$creatable, $rest];
    }

    /**
     * One group per normalised name (an odd name that normalises to nothing stays its own group), named by its first item.
     *
     * @param  list<array{legacy_id: int, names: list<string>, reason: string}>  $items
     * @return list<array{name: string, legacy_ids: list<int>}>
     */
    private function groupByName(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $name = $this->displayName($item['names']);
            $key = NameNormalizer::normalize($name);
            $key = $key === '' ? '#'.$item['legacy_id'] : $key;

            $groups[$key]['name'] ??= $name;
            $groups[$key]['legacy_ids'][] = $item['legacy_id'];
        }

        return array_values($groups);
    }

    /** @param  list<string>  $names */
    private function displayName(array $names): string
    {
        foreach ($names as $name) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $name));

            if ($name !== '') {
                return mb_substr($name, 0, self::NAME_MAX);
            }
        }

        return '';
    }

    private function timestamp(ImportContext $context): string
    {
        return $context->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<int, string>
     */
    private function overridesFor(array $overrides, string $key): array
    {
        $forMaster = $overrides[$key] ?? [];
        $result = [];

        if (is_array($forMaster)) {
            foreach ($forMaster as $legacyId => $slug) {
                if (is_string($slug)) {
                    $result[(int) $legacyId] = $slug;
                }
            }
        }

        return $result;
    }
}
