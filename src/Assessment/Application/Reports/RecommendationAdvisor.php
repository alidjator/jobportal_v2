<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\NormCandidate;
use Src\Assessment\Domain\NormConverter;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\Recommendation;
use Src\Assessment\Domain\RecommendationRuleRepository;

/** Works out the recommendation of a report from the rule of its battery and the standard scores the norms give this candidate; null when the battery has no rule or a key scale has no standard score. */
final readonly class RecommendationAdvisor
{
    public function __construct(private RecommendationRuleRepository $rules, private NormRepository $norms) {}

    /** @param  array<string, float>|null  $scaleScores  the raw score of each scale */
    public function advise(int $testId, ?array $scaleScores, NormCandidate $candidate): ?Recommendation
    {
        $rules = $this->rules->ofTest($testId);

        if ($rules === null || $scaleScores === null) {
            return null;
        }

        $norms = $this->norms->ofTest($testId);
        $standard = [];

        foreach ($scaleScores as $scale => $raw) {
            $standard[(string) $scale] = NormConverter::convert($norms, (string) $scale, $raw, $candidate)?->standardScore;
        }

        return $rules->decide($standard);
    }
}
