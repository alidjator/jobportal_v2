<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * How a battery that issues reports automatically works out the recommendation: the key scales, and two thresholds on their standard scores (the norm table gives them; bands have no
 * order). Every key scale at or above `recommendedMin`: recommended. Any key scale below `notYetBelow`: not yet. Otherwise: consider. A key scale without a standard score (no norm row
 * holds the candidate) gives no recommendation, so the report goes to a psychologist.
 */
final readonly class RecommendationRules
{
    public const int KEY_SCALES_MAX = 50;

    public const float SCORE_MAX = 9999.99;

    /** @param  list<string>  $keyScales */
    public function __construct(public array $keyScales, public float $recommendedMin, public float $notYetBelow) {}

    /**
     * @param  list<string>  $keyScales
     * @param  list<string>  $knownScales  the scales the questions of the test use
     *
     * @throws BusinessRuleViolation `invalid-recommendation-rules` with `errors` on `key_scales`, `recommended_min` or `not_yet_below`
     */
    public static function checked(array $keyScales, float $recommendedMin, float $notYetBelow, array $knownScales): self
    {
        $unique = array_values(array_unique($keyScales));

        if ($unique === [] || count($unique) > self::KEY_SCALES_MAX) {
            throw self::invalid('key_scales', new ErrorMessage('Name between 1 and :max key scales.', ['max' => self::KEY_SCALES_MAX]));
        }

        foreach ($unique as $scale) {
            if (! in_array($scale, $knownScales, true)) {
                throw self::invalid('key_scales', new ErrorMessage('No question of this test uses the scale :scale.', ['scale' => $scale]));
            }
        }

        foreach (['recommended_min' => $recommendedMin, 'not_yet_below' => $notYetBelow] as $field => $value) {
            if (abs($value) > self::SCORE_MAX) {
                throw self::invalid($field, new ErrorMessage('A standard score must lie between -:max and :max.', ['max' => self::SCORE_MAX]));
            }
        }

        if ($notYetBelow > $recommendedMin) {
            throw self::invalid('not_yet_below', 'The threshold for "not yet" cannot be above the threshold for "recommended".');
        }

        return new self($unique, round($recommendedMin, 2), round($notYetBelow, 2));
    }

    /**
     * @param  array<string, float|null>  $standardScores  the standard score of each scale, null when no norm holds the candidate
     */
    public function decide(array $standardScores): ?Recommendation
    {
        $recommended = true;

        foreach ($this->keyScales as $scale) {
            $score = $standardScores[$scale] ?? null;

            if ($score === null) {
                return null;
            }

            if ($score < $this->notYetBelow) {
                return Recommendation::NotYet;
            }

            $recommended = $recommended && $score >= $this->recommendedMin;
        }

        return $recommended ? Recommendation::Recommended : Recommendation::Consider;
    }

    private static function invalid(string $field, string|ErrorMessage $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-recommendation-rules', extensions: ['errors' => [$field => [$message]]]);
    }
}
