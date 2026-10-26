<?php

namespace Src\Assessment\Application\Reports;

use DateTimeImmutable;

/**
 * What the candidate gets to read after a psychologist signed the report of their test: the report number, who signed and when, the plain-language explanation
 * and the score of each scale. The company's full report is not part of it.
 */
final readonly class MyExplanation
{
    /** @param  list<array{scale: string, raw: float, standard: float|null, band: string|null, norm_group: array{age_min: int|null, age_max: int|null, education_rank_min: int|null, education_rank_max: int|null}|null}>  $scales */
    public function __construct(
        public int $invitationId,
        public string $testTitle,
        public string $reportNumber,
        public DateTimeImmutable $signedAt,
        public string $signerName,
        public string $explanation,
        public array $scales,
    ) {}
}
