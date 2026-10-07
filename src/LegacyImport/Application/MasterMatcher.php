<?php

namespace Src\LegacyImport\Application;

use stdClass;

/**
 * Matches a legacy master to the new master by normalised name (any language), because the seeders kept the legacy values and order but not the legacy ids.
 * A mapping file (`{"<legacy id>": "<slug>"}` per master) wins over the names. An item that matches more than one new item is not guessed: it is reported.
 */
final readonly class MasterMatcher
{
    public function __construct(private LegacySource $legacy, private NewMasterReader $masters) {}

    /** @param  array<int, string>  $overrides  slug by legacy id, from the mapping file */
    public function match(MasterSpec $spec, array $overrides = []): MasterMatch
    {
        $new = $this->masters->rows($spec->newTable);
        $byName = [];
        $bySlug = [];

        foreach ($new as $row) {
            $bySlug[$row['slug']] = $row['id'];

            foreach ($row['names'] as $name) {
                $normalized = NameNormalizer::normalize($name);

                if ($normalized !== '') {
                    $byName[$normalized][$row['id']] = $row['id'];
                }
            }
        }

        $matched = [];
        $unmatched = [];

        foreach ($this->legacyNames($spec) as $legacyId => $names) {
            $override = $overrides[$legacyId] ?? null;

            if ($override !== null) {
                if (isset($bySlug[$override])) {
                    $matched[$legacyId] = $bySlug[$override];
                } else {
                    $unmatched[] = ['legacy_id' => $legacyId, 'names' => $names, 'reason' => 'override-slug-unknown'];
                }

                continue;
            }

            $candidates = [];

            foreach ($names as $name) {
                foreach ($byName[NameNormalizer::normalize($name)] ?? [] as $id) {
                    $candidates[$id] = $id;
                }
            }

            if (count($candidates) === 1) {
                $matched[$legacyId] = (int) reset($candidates);
            } else {
                $unmatched[] = ['legacy_id' => $legacyId, 'names' => $names, 'reason' => $candidates === [] ? 'no-match' : 'ambiguous'];
            }
        }

        return new MasterMatch($matched, $unmatched);
    }

    /** @return array<int, list<string>> the distinct names of each legacy item, by legacy id */
    private function legacyNames(MasterSpec $spec): array
    {
        $names = [];

        if ($spec->translationTable === null) {
            foreach ($this->legacy->all($spec->legacyTable, ['id', $spec->nameColumn]) as $row) {
                $names[(int) $row->id] = [(string) $row->{$spec->nameColumn}];
            }

            return $names;
        }

        foreach ($this->legacy->all($spec->legacyTable, ['id']) as $row) {
            $names[(int) $row->id] = [];
        }

        $foreignKey = (string) $spec->foreignKey;

        foreach ($this->legacy->all($spec->translationTable, [$foreignKey, 'name']) as $row) {
            /** @var stdClass $row */
            $id = (int) $row->{$foreignKey};

            if (isset($names[$id]) && ! in_array((string) $row->name, $names[$id], true)) {
                $names[$id][] = (string) $row->name;
            }
        }

        return $names;
    }
}
