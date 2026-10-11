<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The rules of a norm table: well-formed rows, and no two ranges of one scale overlapping. */
final class NormRules
{
    public const int ROWS_MAX = 500;

    public const float SCORE_MAX = 9999.99;

    /**
     * @param  list<Norm>  $norms
     * @param  list<string>  $knownScales  the scales the questions of the test use
     * @return list<Norm> the norms with a clean band, ordered by scale and range
     *
     * @throws BusinessRuleViolation `invalid-norms` with `errors` on `norms`
     */
    public static function check(array $norms, array $knownScales): array
    {
        if (count($norms) > self::ROWS_MAX) {
            throw self::invalid('A norm table may have at most '.self::ROWS_MAX.' rows.');
        }

        $clean = [];

        foreach ($norms as $norm) {
            $band = trim($norm->band);

            if (! in_array($norm->scaleKey, $knownScales, true)) {
                throw self::invalid("No question of this test uses the scale {$norm->scaleKey}.");
            }

            if ($norm->rawMin > $norm->rawMax) {
                throw self::invalid('The lowest raw score of a range cannot exceed its highest.');
            }

            if (abs($norm->standardScore) > self::SCORE_MAX) {
                throw self::invalid('A standard score must lie between -'.self::SCORE_MAX.' and '.self::SCORE_MAX.'.');
            }

            if ($band === '' || mb_strlen($band) > 40) {
                throw self::invalid('Every row needs a band of 1 to 40 characters.');
            }

            $clean[] = new Norm($norm->scaleKey, $norm->rawMin, $norm->rawMax, round($norm->standardScore, 2), $band);
        }

        usort($clean, static fn (Norm $a, Norm $b): int => [$a->scaleKey, $a->rawMin] <=> [$b->scaleKey, $b->rawMin]);

        for ($i = 1; $i < count($clean); $i++) {
            if ($clean[$i]->scaleKey === $clean[$i - 1]->scaleKey && $clean[$i]->rawMin <= $clean[$i - 1]->rawMax) {
                throw self::invalid("The ranges of the scale {$clean[$i]->scaleKey} overlap.");
            }
        }

        return $clean;
    }

    private static function invalid(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-norms', extensions: ['errors' => ['norms' => [$message]]]);
    }
}
