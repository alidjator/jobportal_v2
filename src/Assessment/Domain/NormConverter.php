<?php

namespace Src\Assessment\Domain;

/** Turns the raw score of a scale into its standard score and band with the provider's norm table. */
final class NormConverter
{
    /**
     * The norm whose range holds the raw score (rounded to a whole number) and whose group holds the candidate, or null when none does. The most specific row wins:
     * both groups set beat one, one beats none, and within a tie the narrower age range.
     *
     * @param  list<Norm>  $norms
     */
    public static function convert(array $norms, string $scaleKey, float $raw, NormCandidate $candidate = new NormCandidate): ?Norm
    {
        $rounded = (int) round($raw);
        $best = null;

        foreach ($norms as $norm) {
            if ($norm->scaleKey !== $scaleKey || $rounded < $norm->rawMin || $rounded > $norm->rawMax || ! $norm->holdsFor($candidate)) {
                continue;
            }

            if ($best === null || self::rank($norm) < self::rank($best)) {
                $best = $norm;
            }
        }

        return $best;
    }

    /** @return array{int, int} smaller is more specific */
    private static function rank(Norm $norm): array
    {
        $groups = (int) $norm->hasAgeGroup() + (int) $norm->hasEducationGroup();
        $width = $norm->hasAgeGroup() ? ($norm->ageMax ?? 200) - ($norm->ageMin ?? 0) : PHP_INT_MAX;

        return [-$groups, $width];
    }
}
