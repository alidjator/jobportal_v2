<?php

namespace Src\LegacyImport\Application;

/**
 * Finds skill masters in the free text of the legacy `hard_skill` and `soft_skill` columns. The text (HTML from an editor) is split into items (lines, list items,
 * commas, semicolons, bullets), and each item is matched to a skill of the right kind by normalised name, in any language. An item that matches two skills is
 * ambiguous and is not guessed; an item that matches none, or that is too long to be a skill name, is unmatched.
 */
final class SkillTextMatcher
{
    /** An item longer than this is a sentence, not a skill name. */
    private const int ITEM_MAX = 60;

    /** @var array<string, array<string, list<int>>>|null kind => normalised name => skill ids */
    private ?array $index = null;

    public function __construct(private readonly NewMasterReader $masters) {}

    /** @return array{matched: list<int>, unmatched: int, ambiguous: int} */
    public function match(?string $html, string $kind): array
    {
        $result = ['matched' => [], 'unmatched' => 0, 'ambiguous' => 0];

        foreach ($this->items($html) as $item) {
            $ids = mb_strlen($item) > self::ITEM_MAX ? [] : ($this->index()[$kind][NameNormalizer::normalize($item)] ?? []);

            if (count($ids) === 1) {
                $result['matched'][] = $ids[0];
            } elseif ($ids === []) {
                $result['unmatched']++;
            } else {
                $result['ambiguous']++;
            }
        }

        $result['matched'] = array_values(array_unique($result['matched']));

        return $result;
    }

    /** @return list<string> */
    private function items(?string $html): array
    {
        $text = LegacyText::plain($html);

        if ($text === null) {
            return [];
        }

        $items = [];

        foreach (preg_split('/[\n,;|\x{2022}\x{00B7}]+/u', $text) ?: [] as $part) {
            $item = trim((string) preg_replace('/^(?:[\s\-*\x{2022}\x{00B7}]+|\d+[.)]\s+)/u', '', $part));

            if ($item !== '' && NameNormalizer::normalize($item) !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array<string, array<string, list<int>>> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];

        foreach ($this->masters->skills() as $skill) {
            foreach ($skill['names'] as $name) {
                $key = NameNormalizer::normalize($name);

                if ($key !== '' && ! in_array($skill['id'], $index[$skill['kind']][$key] ?? [], true)) {
                    $index[$skill['kind']][$key][] = $skill['id'];
                }
            }
        }

        return $this->index = $index;
    }
}
