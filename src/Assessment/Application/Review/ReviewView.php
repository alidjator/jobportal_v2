<?php

namespace Src\Assessment\Application\Review;

use DateTimeImmutable;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\SpeedColumnResult;

/**
 * One report for review: the draft text, the scores with their norms, what the candidate did in the speed-column sections, the drawings with the scores given so
 * far and whether the psychologist may sign right now (`signingProblems` lists what stands in the way).
 */
final readonly class ReviewView
{
    /**
     * @param  list<array{scale: string, raw: float, standard: float|null, band: string|null}>  $scales
     * @param  list<SpeedColumnResult>  $columns
     * @param  list<array{question_id: int, text: string, max_score: int, score: float|null, note: string|null, mime_type: string}>  $drawings
     * @param  list<string>  $signingProblems
     */
    public function __construct(
        public Report $report,
        public int $invitationId,
        public string $candidateName,
        public string $testTitle,
        public ?DateTimeImmutable $submittedAt,
        public IntegrityTier $integrityTier,
        public array $scales,
        public array $columns,
        public array $drawings,
        public array $signingProblems,
    ) {}
}
