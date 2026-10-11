<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\NormConverter;
use Src\Assessment\Domain\NormRepository;

/** The raw score of each scale with the standard score and band the norms of the test give it; both are null when no norm range holds the raw score. */
final readonly class ScaleSummaries
{
    public function __construct(private NormRepository $norms) {}

    /**
     * @param  array<string, float>  $scaleScores
     * @return list<array{scale: string, raw: float, standard: float|null, band: string|null}>
     */
    public function rows(int $testId, array $scaleScores): array
    {
        $norms = $this->norms->ofTest($testId);
        $rows = [];

        foreach ($scaleScores as $scale => $raw) {
            $norm = NormConverter::convert($norms, (string) $scale, $raw);
            $rows[] = ['scale' => (string) $scale, 'raw' => $raw, 'standard' => $norm?->standardScore, 'band' => $norm?->band];
        }

        return $rows;
    }
}
