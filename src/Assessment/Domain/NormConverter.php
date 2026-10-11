<?php

namespace Src\Assessment\Domain;

/** Turns the raw score of a scale into its standard score and band with the provider's norm table. */
final class NormConverter
{
    /**
     * The norm whose range holds the raw score (rounded to a whole number), or null when no range does.
     *
     * @param  list<Norm>  $norms
     */
    public static function convert(array $norms, string $scaleKey, float $raw): ?Norm
    {
        $rounded = (int) round($raw);

        foreach ($norms as $norm) {
            if ($norm->scaleKey === $scaleKey && $rounded >= $norm->rawMin && $rounded <= $norm->rawMax) {
                return $norm;
            }
        }

        return null;
    }
}
