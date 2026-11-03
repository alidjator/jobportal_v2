<?php

namespace Src\Assessment\Domain;

/**
 * The most/least questions of the DISC family and their scoring (`docs/design/phase-8-disc-profile.md`). A statement carries a dimension (its `scale_key`: `d`, `i`, `s`, `c`); the candidate picks one statement as the most
 * like them and a different one as the least like them. Each answer adds 1 to `<dimension>_most` of the first and 1 to `<dimension>_least` of the second, and the third graph, `<dimension>_change`, is most minus least. These
 * twelve keys (four dimensions times three graphs) are the scales of such a battery: the norms, the report template and the recommendation name them, never the bare dimension.
 */
final class MostLeast
{
    /** A dimension is short and holds no underscore, which separates it from the graph in the scale key. */
    public const string DIMENSION_PATTERN = '/^[a-z0-9]{1,20}$/';

    public const array GRAPHS = ['most', 'least', 'change'];

    /** The standard score from which a dimension counts as high in a graph, until the norm rows of the battery say it. */
    public const float DEFAULT_HIGH_MARK = 55.0;

    /** The bands an author calls high, in lower case: the lowest standard score among their rows is the battery's high mark. */
    private const array HIGH_BANDS = ['high', 'tinggi'];

    public static function key(string $dimension, string $graph): string
    {
        return $dimension.'_'.$graph;
    }

    /**
     * The scale keys of the given dimensions, sorted.
     *
     * @param  list<string>  $dimensions
     * @return list<string>
     */
    public static function derivedKeys(array $dimensions): array
    {
        $keys = [];

        foreach ($dimensions as $dimension) {
            foreach (self::GRAPHS as $graph) {
                $keys[] = self::key($dimension, $graph);
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * The dimensions of the statements of a question, sorted and without repeats.
     *
     * @return list<string>
     */
    public static function dimensionsOf(Question $question): array
    {
        $dimensions = [];

        foreach ($question->options as $option) {
            if ($option->scaleKey !== null) {
                $dimensions[$option->scaleKey] = true;
            }
        }

        $dimensions = array_map('strval', array_keys($dimensions));
        sort($dimensions);

        return $dimensions;
    }

    /**
     * What an answer adds to the scales: 1 to the `_most` of the statement picked as most and 1 to the `_least` of the one picked as least. An answer that does not name two different statements adds nothing.
     *
     * @return array<string, float>
     */
    public static function contributions(Question $question, ?Answer $answer): array
    {
        $positions = $answer->optionPositions ?? [];

        if (count($positions) !== 2 || $positions[0] === $positions[1]) {
            return [];
        }

        $most = $question->options[$positions[0] - 1]->scaleKey ?? null;
        $least = $question->options[$positions[1] - 1]->scaleKey ?? null;

        if ($most === null || $least === null) {
            return [];
        }

        return [self::key($most, 'most') => 1.0, self::key($least, 'least') => 1.0];
    }

    /**
     * The scores with `<dimension>_change` (most minus least) set for every dimension that has both counts.
     *
     * @param  array<string, float>  $scales
     * @return array<string, float>
     */
    public static function withChanges(array $scales): array
    {
        foreach (self::dimensionsOfScales(array_keys($scales)) as $dimension) {
            $scales[self::key($dimension, 'change')] = ($scales[self::key($dimension, 'most')] ?? 0.0) - ($scales[self::key($dimension, 'least')] ?? 0.0);
        }

        return $scales;
    }

    /**
     * The dimensions behind the `_most` keys among the scale keys, sorted.
     *
     * @param  list<int|string>  $keys
     * @return list<string>
     */
    public static function dimensionsOfScales(array $keys): array
    {
        $dimensions = [];

        foreach ($keys as $key) {
            if (str_ends_with((string) $key, '_most')) {
                $dimensions[] = substr((string) $key, 0, -5);
            }
        }

        sort($dimensions);

        return $dimensions;
    }

    /**
     * The data of the three charts of a DISC report from the rows of its scales (`scale`, `standard`): the dimensions and, per graph, the standard score of each dimension in the same order (null where no norm
     * covers the raw score). Null when the rows hold no most/least scales.
     *
     * @param  list<array{scale: string, standard: float|null}>  $rows
     * @return array{dimensions: list<string>, graphs: array<string, list<float|null>>}|null
     */
    public static function chart(array $rows): ?array
    {
        $standards = [];

        foreach ($rows as $row) {
            $standards[$row['scale']] = $row['standard'];
        }

        $dimensions = self::dimensionsOfScales(array_keys($standards));

        if ($dimensions === []) {
            return null;
        }

        $graphs = [];

        foreach (self::GRAPHS as $graph) {
            $graphs[$graph] = array_map(static fn (string $dimension): ?float => $standards[self::key($dimension, $graph)] ?? null, $dimensions);
        }

        return ['dimensions' => $dimensions, 'graphs' => $graphs];
    }

    /**
     * The standard score from which a dimension counts as high: the lowest standard score among the norm rows whose band the author calls high ("high" or "tinggi"), else the default.
     *
     * @param  list<Norm>  $norms
     */
    public static function highMark(array $norms): float
    {
        $marks = [];

        foreach ($norms as $norm) {
            if (in_array(mb_strtolower(trim($norm->band)), self::HIGH_BANDS, true)) {
                $marks[] = $norm->standardScore;
            }
        }

        return $marks === [] ? self::DEFAULT_HIGH_MARK : min($marks);
    }

    /**
     * The dimensions whose standard score in the graph reaches the high mark, highest first (ties by key), as letters (`DI`), or `-` when none does.
     *
     * @param  array<string, float>  $scaleScores
     * @param  list<Norm>  $norms
     */
    public static function profile(array $scaleScores, array $norms, NormCandidate $candidate, string $graph): string
    {
        $mark = self::highMark($norms);
        $high = [];

        foreach (self::dimensionsOfScales(array_keys($scaleScores)) as $dimension) {
            $key = self::key($dimension, $graph);
            $norm = isset($scaleScores[$key]) ? NormConverter::convert($norms, $key, $scaleScores[$key], $candidate) : null;

            if ($norm !== null && $norm->standardScore >= $mark) {
                $high[] = [$norm->standardScore, $dimension];
            }
        }

        usort($high, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return $high === [] ? '-' : implode('', array_map(static fn (array $entry): string => strtoupper($entry[1]), $high));
    }
}
