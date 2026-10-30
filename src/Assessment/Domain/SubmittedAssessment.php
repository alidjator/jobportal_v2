<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * The latest submitted test of one application with what is stored of its result. `status` and the result fields are null when the attempt was submitted but not scored
 * yet. `reportNumber` is the number of the signed report of a psychological battery, null before it is signed.
 */
final readonly class SubmittedAssessment
{
    /**
     * @param  list<array<string, mixed>>  $sectionScores
     * @param  array<string, float>|null  $scaleScores
     */
    public function __construct(
        public int $applicationId,
        public int $invitationId,
        public int $testId,
        public ?int $stageId,
        public int $attemptId,
        public DateTimeImmutable $submittedAt,
        public NormCandidate $candidate,
        public ?ResultStatus $status,
        public ?float $percentage,
        public ?ResultBand $band,
        public ?IntegrityTier $integrityTier,
        public array $sectionScores,
        public ?array $scaleScores,
        public ?string $reportNumber,
    ) {}
}
