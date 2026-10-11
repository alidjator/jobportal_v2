<?php

namespace Src\Assessment\Domain;

/**
 * The scoring of a submitted attempt. A skill test has points, a percentage and a band; a psychological battery has the raw score of each scale (the
 * norms and the report come later). `sectionScores` holds one entry per section, shaped by its format.
 */
final readonly class AttemptResult
{
    /**
     * @param  list<array<string, mixed>>  $sectionScores
     * @param  array<string, float>|null  $scaleScores
     */
    public function __construct(
        public int $attemptId,
        public ResultStatus $status,
        public array $sectionScores,
        public ?array $scaleScores,
        public ?float $earnedPoints,
        public ?float $maxPoints,
        public ?float $percentage,
        public ?ResultBand $band,
        public IntegrityTier $integrityTier,
    ) {}
}
