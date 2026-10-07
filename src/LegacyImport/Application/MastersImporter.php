<?php

namespace Src\LegacyImport\Application;

/**
 * Works out which new master each legacy master item became (see `MasterMatcher`) and remembers it in the id map under `master.<key>`, so every later importer
 * finds its masters there. Unlike the other importers it is worked out again at each run (a corrected mapping file takes effect); the items without a
 * counterpart are listed in the report by name (configuration, not personal data).
 */
final readonly class MastersImporter implements Importer
{
    public const string ENTITY = 'masters';

    public function __construct(private MasterMatcher $matcher) {}

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

            $context->report->read($entity, count($match->matched) + count($match->unmatched));
            $context->report->imported($entity, count($match->matched));

            foreach ($match->unmatched as $item) {
                $context->report->skipped($entity, $item['legacy_id'], $item['reason']);
                $context->report->unmatched($entity, ['legacy_id' => $item['legacy_id'], 'names' => $item['names'], 'reason' => $item['reason']]);
            }

            if ($context->write) {
                $context->idMap->replace($entity, $match->matched, $context->clock->now());
            }
        }
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
