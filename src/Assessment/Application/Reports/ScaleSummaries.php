<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\NormCandidate;
use Src\Assessment\Domain\NormConverter;
use Src\Assessment\Domain\NormRepository;

/**
 * The raw score of each scale with the standard score and band the norms of the test give it for the candidate's age and education; both are null when no norm row
 * holds the raw score and the candidate. `norm_group` names the group of the row that was used, null for a row that applies to everyone.
 */
final readonly class ScaleSummaries
{
    public function __construct(private NormRepository $norms) {}

    /**
     * @param  array<string, float>  $scaleScores
     * @return list<array{scale: string, raw: float, standard: float|null, band: string|null, norm_group: array{age_min: int|null, age_max: int|null, education_rank_min: int|null, education_rank_max: int|null}|null}>
     */
    public function rows(int $testId, array $scaleScores, NormCandidate $candidate = new NormCandidate): array
    {
        $norms = $this->norms->ofTest($testId);
        $rows = [];

        foreach ($scaleScores as $scale => $raw) {
            $norm = NormConverter::convert($norms, (string) $scale, $raw, $candidate);
            $rows[] = ['scale' => (string) $scale, 'raw' => $raw, 'standard' => $norm?->standardScore, 'band' => $norm?->band, 'norm_group' => $norm?->group()];
        }

        return $rows;
    }
}
